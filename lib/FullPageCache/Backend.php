<?php
namespace FullPageCache;

use Webframework\Request\Request;

class Backend {

	/**
	 * @var BackendRedisConnection
	 */
	protected $redisConnection;

	/**
	 * @var int
	 */
	protected $bzCompressionLevel;

	/**
	 * @var int
	 */
	protected $minCompressionByteSize;

	/**
	 * @var int
	 */
	protected $jsonOptions;

	const COMPRESSION_TYPE_GZIP = 'gz';
	const COMPRESSION_TYPE_NONE = 'rv';

    /**
     * Backend constructor.
     * @param BackendRedisConnection $redisConnection
     * @param int $bzCompressionLevel
     * @param int $minCompressionByteSize
     */
	public function __construct(BackendRedisConnection $redisConnection, int $bzCompressionLevel = 7, int $minCompressionByteSize = 2048) {

		$this->redisConnection = $redisConnection;

		$this->jsonOptions = JSON_UNESCAPED_SLASHES;
		$this->bzCompressionLevel = $bzCompressionLevel;
		$this->minCompressionByteSize = $minCompressionByteSize;
	}

	/**
	 * @param string $requestKey
	 * @return Page|null
	 */
	public function getPage(string $requestKey): ?Page {
		try {
			$pageCacheKey = self::CACHE_KEY_PAGE_.$requestKey;

			$pageData = $this->redisConnection->get($pageCacheKey);
			if(!$pageData) {
				return null;
			}

			$pageMetaDataJson = $this->redisConnection->hGet(self::CACHE_KEY_LIST, $requestKey);
			if(!$pageMetaDataJson) {
				return null;
			}
			$pageMetaData = json_decode($pageMetaDataJson);
			if(!is_object($pageMetaData) || !($pageMetaData instanceof \stdClass)) {
				return null;
			}

			$compressionType = substr($pageData, 0, 2);
			$pageData = substr($pageData, 3);
			if($compressionType == self::COMPRESSION_TYPE_GZIP) {
				$uncompressedPageData = gzuncompress($pageData);
				if($uncompressedPageData === false) {
					return null;
				}
				$pageData = $uncompressedPageData;
				unset($uncompressedPageData);
			}

			$page = new Page($pageMetaData->pageKey, $pageData, $pageMetaData->responseHeaders);
			return $page;

		} catch(\Exception $e) {
			error_log((string)$e);
		}
		return null;
	}

	const CACHE_KEY_LIST    = 'list';
	const CACHE_KEY_QUEUE   = 'queue';
	const CACHE_KEY_PAGE_   = 'page_';

	/**
	 * @param Request $request
	 * @param string $pageKey
	 * @param int $refreshInterval
	 * @param array $responseHeaders
	 */
	public function registerPage(Request $request, $pageKey, $refreshInterval, array $responseHeaders): void {
		try {
			$requestKey = $this->getRequestKey($request);

			$queueData = new \stdClass();
			$queueData->url = (string)$request;
			$queueData->refreshInterval = $refreshInterval;
			$queueData->pageKey = $pageKey;
			$queueData->requestKey = $requestKey;
			$queueData->responseHeaders = $responseHeaders;

			$queueDataJson = json_encode($queueData, $this->jsonOptions);

			/**
			 * Adds all the specified members with the specified scores to the sorted set stored at key.
			 * If a specified member is already a member of the sorted set, the score is updated and the element reinserted at the right position to ensure the correct ordering.
			 */
            $this->redisConnection->zAdd(self::CACHE_KEY_QUEUE, 0, $requestKey);

			/**
			 * Sets field in the hash stored at key to value, only if field does not yet exist.
			 * If key does not exist, a new key holding a hash is created.
			 * If field already exists, this operation has no effect.
			 * https://redis.io/commands/hsetnx
			 */
            $this->redisConnection->hSetNx(self::CACHE_KEY_LIST, $requestKey, $queueDataJson);

		} catch(\Exception $e) {
			error_log((string)$e);
		}
	}

	/**
	 * @param string $requestKey
	 * @param int $refreshInterval
	 * @param int $expireInterval
	 * @param string $responseBody
	 */
	public function storePage($requestKey, $refreshInterval, $expireInterval, $responseBody): void {
		try {
			/**
			 * Sets field in the hash stored at key to value, only if field does not yet exist.
			 * If key does not exist, a new key holding a hash is created.
			 * If field already exists, this operation has no effect.
			 * https://redis.io/commands/hsetnx
			 */
			$cacheKey = self::CACHE_KEY_PAGE_.$requestKey;

			$storeResponseBody = self::COMPRESSION_TYPE_NONE.':'.$responseBody; // rv: raw value
			if(strlen($responseBody) > $this->minCompressionByteSize) { // don't compress if too small
				$compressedResponseBody = gzcompress($responseBody, $this->bzCompressionLevel);
				if($compressedResponseBody !== false) {
					$storeResponseBody = self::COMPRESSION_TYPE_GZIP.':'.$compressedResponseBody; // gz: compressed
				}
			}

            $this->redisConnection->set($cacheKey, $storeResponseBody);
            $this->redisConnection->expire($cacheKey, $refreshInterval+$expireInterval);
            $this->redisConnection->zAdd(self::CACHE_KEY_QUEUE, time()+$refreshInterval, $requestKey);

		} catch(\Exception $e) {
			error_log((string)$e);
		}
	}

	/**
	 * @param string $requestKey
	 */
	public function removePage(string $requestKey): void {
		try {
			/**
			 * Sets field in the hash stored at key to value, only if field does not yet exist.
			 * If key does not exist, a new key holding a hash is created.
			 * If field already exists, this operation has no effect.
			 * https://redis.io/commands/hsetnx
			 */
			$cacheKey = self::CACHE_KEY_PAGE_.$requestKey;

            $this->redisConnection->del($cacheKey);
            $this->redisConnection->hDel(self::CACHE_KEY_LIST, $requestKey);
            $this->redisConnection->zRem(self::CACHE_KEY_QUEUE, $requestKey);

		} catch(\Exception $e) {
			error_log((string)$e);
		}
	}

	/**
	 * @return array
	 */
	public function getPagesToRefresh(): array {
		try {
			$pageList = $this->redisConnection->zRangeByScore(self::CACHE_KEY_QUEUE, 0, time());
			if(is_array($pageList)) {
				return $pageList;
			}
		} catch(\Exception $e) {
			error_log((string)$e);
		}
		return array();
	}

	/**
	 * @param array $requestKeys
	 * @return array
	 */
	public function getPagesMetaData(array $requestKeys): array {
		if(empty($requestKeys)) {
			return array();
		}
		try {
			$pagesMetaDataJson = $this->redisConnection->hMGet(self::CACHE_KEY_LIST, $requestKeys);
			if(is_array($pagesMetaDataJson)) {
				$pages = array();
				foreach($pagesMetaDataJson as $pageMetaDataJson) {
					$pageMetaData = json_decode($pageMetaDataJson);
					if($pageMetaData && $pageMetaData instanceof \stdClass) {
						$pages[$pageMetaData->requestKey] = $pageMetaData;
					}
				}
				return $pages;
			}
		} catch(\Exception $e) {
			error_log((string)$e);
		}
		return array();
	}

	/**
	 * flush entire cache
	 */
	public function flush(): void {
		try {
            $this->redisConnection->flushAll();
		} catch(\Exception $e) {
			error_log((string)$e);
		}
	}

	/**
	 * set all pages to be refreshed by the cache worker
	 */
	public function refreshAll(): void {
		try {
			$keys = $this->redisConnection->hKeys(self::CACHE_KEY_LIST);
			foreach($keys as $key) {
                $this->redisConnection->zAdd(self::CACHE_KEY_QUEUE, 0, $key);
			}
		} catch(\Exception $e) {
			error_log((string)$e);
		}
	}

	/**
	 * @return BackendStats|null
	 */
	public function getStats(): ?BackendStats {
		try {
			$memorySection = $this->redisConnection->info('memory');
			$memory = intval($memorySection['used_memory']);

			$keys = $this->redisConnection->hKeys(self::CACHE_KEY_LIST);

			$stats = new BackendStats(count($keys), $memory);
			return $stats;

		} catch(\Exception $e) {
			error_log((string)$e);
		}
		return null;
	}

    /**
     * example: https_www.domain.com_my-path-xyz
     * @return string
     */
    public function getRequestKey(Request $request): string {
        $requestKey = array();
        $requestKey[] = $request->getScheme();
        $requestKey[] = $request->getHost();
        $requestKey[] = str_replace('/', '-', trim($request->getRequestPathFlat(), '/'));
        return implode('_', $requestKey);
    }
}