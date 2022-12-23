<?php
namespace FullPageCache;

use Webframework\FileSystem\File;
use Webframework\Service\Log\LogService;
use Webframework\Worker\AbstractWorker;

class CacheWorker extends AbstractWorker {

    const USER_AGENT = 'fullpage-cache-refresh-worker';

    protected Backend $backend;

    protected Config $config;

    protected int $parallelRequests = 8;

    protected int $parallelRequestsChunkSize = 32;

    protected int $expireInterval;

    /**
     * @var resource
     */
    protected $multiCurl;

    protected ?File $deploymentHashFile = null;

    protected ?string $deploymentHash = null;
    
    protected ?LogService $logService = null;

	public function __construct(
        Config $config, 
        Backend $backend, 
        string $name, 
        float $workInterval, 
        File $deploymentHashFile = null, 
        $memoryLimit = null, 
        $timeLimit = null, 
        LogService $logService = null
    ) {
		$this->config = $config;
		$this->backend = $backend;
		$this->expireInterval = $this->config->getExpireInterval();
        $this->deploymentHashFile = $deploymentHashFile;
        $this->logService = $logService;
		parent::__construct($name, $workInterval, $memoryLimit, $timeLimit);
	}

    /**
     * @return bool
     */
	protected function detectDeployment(): bool {
	    if(!$this->deploymentHashFile) {
	        return false;
        }
        // initial hash read
        if($this->deploymentHash === null) {
            $this->deploymentHash = $this->deploymentHashFile->getContent();
        }
        // hash differs from before
        if($this->deploymentHash != $this->deploymentHashFile->getContent()) {
            return true;
        }
        // store last hash
        $this->deploymentHash = $this->deploymentHashFile->getContent();
        return false;
    }

    protected function init(): void {
		$this->multiCurl = curl_multi_init();
	}

	public function shutdown(): void {
		if($this->multiCurl) {
			curl_multi_close($this->multiCurl);
		}
	}

	protected function work(): void {

		// fetch list from backend
	    $requestKeys = $this->backend->getPagesToRefresh();

		// fetch metadata for pages
	    $pagesMetaData = $this->backend->getPagesMetaData($requestKeys);

		$requests = [];
		foreach($pagesMetaData as $pageMetaData) {
			$requests[$pageMetaData->requestKey] = $pageMetaData->url;
		}

		// break down list of requests into chunks
		// this prevents too many pages to be in memory at the same time
		$requestChunks = array_chunk($requests, $this->parallelRequestsChunkSize, true);
		foreach($requestChunks as $chunkOfRequests) {

			$results = $this->fetch($chunkOfRequests);

			foreach($results as $requestKey => $result) {
				[
					$httpStatus,
					$returnData,
					$curlErrno,
					$curlError,
					$curlDebug
				] = $result;

				if($httpStatus == 400 || $httpStatus == 405 || $httpStatus == 403) {
					// remove page from list for these statuses but also log as error
					$this->log('cache fetch for '.$requestKey.' failed: '.$httpStatus.', error: '.$curlError.'('.$curlErrno.'), info: '.print_r($curlDebug, true));
					$this->log('remove from cache '.$requestKey.' response: '.$httpStatus);
					$this->backend->removePage($requestKey);
					continue;
                }
				
				if($httpStatus == 404 || $httpStatus == 410 || $httpStatus == 301 || $httpStatus == 302) {
					// remove page from list for these statuses
					$this->log('remove from cache '.$requestKey.' response: '.$httpStatus);
					$this->backend->removePage($requestKey);
					continue;
				}

				if($httpStatus <> 200 || $curlErrno != CURLE_OK) {
					$this->log('cache fetch for '.$requestKey.' failed: '.$httpStatus.', error: '.$curlError.'('.$curlErrno.'), info: '.print_r($curlDebug, true));
					continue;
				}
				$pageMetaData = array_key_exists($requestKey, $pagesMetaData) ? $pagesMetaData[$requestKey] : null;
				if(!$pageMetaData) {
					continue;
				}
				$this->backend->storePage($requestKey, $pageMetaData->refreshInterval, $this->expireInterval, $returnData);
			}
		}
    }

    protected function fetch(array $requests): array {
	    if(empty($requests)) {
	    	return [];
	    }

	    $handles = [];
	    $fetchTimeout = $this->config->getCacheClientFetchTimeout();
	    $ignoreSslErrors = $this->config->isCacheClientIgnoreSslErrors();

	    foreach($requests as $p => $request) {
		    $ch = curl_init();
		    curl_setopt($ch, CURLOPT_URL, $request);          // set URL
		    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);      // return received data on curl_exec()
		    curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
		    curl_setopt($ch, CURLOPT_TIMEOUT, $fetchTimeout);
			if($ignoreSslErrors) {
			    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			}
		    $handles[$request] = [$ch, $p];
	    }

	    $i=0;
	    $keys = array_keys($requests);
	    $requestLength = count($requests);
	    while($i<$requestLength) {
		    for($h=0;$h<$this->parallelRequests;$h++) {
			    if($i<$requestLength) {
				    [$ch, ] = $handles[$requests[$keys[$i]]];
				    curl_multi_add_handle($this->multiCurl, $ch);
				    $i++;
			    }
		    }
		    $running = null;
		    do {
			    curl_multi_exec($this->multiCurl, $running);
			    usleep(100); // 1/10 ms
		    } while ($running > 0);
	    }

        $results = [];
        
	    foreach($handles as $set) {
		    [$ch, $p] = $set;

		    $returnData = curl_multi_getcontent($ch);
		    $httpStatus = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$curlErrno = curl_errno($ch);
			$curlError = curl_error($ch);
			$curlDebug = $curlErrno == CURLE_OK ? [] : curl_getinfo($ch);

		    $result = [
			    $httpStatus,
			    $returnData,
			    $curlErrno,
				$curlError,
			    $curlDebug
            ];
		    $results[$p] = $result;
		    curl_multi_remove_handle($this->multiCurl, $ch);
	    }

	    return $results;
    }
    
    protected function log(string $text): void {
        if($this->logService) {
            $this->logService->log($text, 'fullpage-cache.log');
        }
    }
}