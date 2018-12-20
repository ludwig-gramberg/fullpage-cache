<?php
namespace FullPageCache;

use Webframework\Request\Request;

class CacheService {

	/**
	 * @var Config
	 */
	protected $config;

	/**
	 * @var Backend
	 */
	protected $backend;

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var bool
	 */
	protected $isCacheClient;

	/**
	 * @var array
	 */
	protected $renderTags;

    /**
     * CacheService constructor.
     * @param Config $config
     * @param Backend $backend
     * @param Request $request
     */
	public function __construct(Config $config, Backend $backend, Request $request) {
	    $this->request = $request;
		$this->config = $config;
		$this->backend = $backend;
		$this->isCacheClient = $this->request->getUserAgent() == CacheWorker::USER_AGENT;
	}

	/**
	 * @return Page|null
	 */
	public function run(): ?Page {

	    // do not trigger for cache worker
		if($this->isCacheClient) {
			return null;
		}

		// allowed schemes
		$allowedSchemes = $this->config->getSchemes();
		if(!in_array($this->request->getScheme(), $allowedSchemes)) {
			return null;
		}

		// allowed host names
		$allowedHostNames = $this->config->getDomains();
		if(!in_array($this->request->getHost(), $allowedHostNames)) {
			return null;
		}

		$requestKey = $this->backend->getRequestKey($this->request);
		$page = $this->backend->getPage($requestKey);
		if(!$page) {
			// cache miss? we're finished here, continue
			return null;
		}
		// cache hit

		// use cache callback
		if($this->config->hasUseCacheCallback()) {
			$useCache = call_user_func($this->config->getUseCacheCallback(), $page->getKey());
			if(!$useCache) {
				return null;
			}
		}

		// tags callback
		$this->renderTags($page);

		// post processing
		if($this->config->hasPostProcessCallback()) {
			call_user_func($this->config->getPostProcessCallback(), $page);
		}

		return $page;
	}

    /**
     * @return bool
     */
	protected function shouldRenderTag(string $tag): bool {
	    if($this->renderTags === null) {
            $this->renderTags = $this->config->hasProcessTagsCallback()
                ? call_user_func($this->config->getProcessTagsCallback())
                : [];
        }
        return in_array($tag, $this->renderTags);
    }

	/**
	 * @param Page $page
	 */
	protected function renderTags(Page $page): void {
		$body = $page->getBody();
		preg_match_all('/\<!\-\-FPC:(.+)\-\-\>/U', $body, $m);
		foreach($m[1] as $bodyTag) {
			$bodyTagQuoted = preg_quote($bodyTag);
			if($this->shouldRenderTag($bodyTag)) {
				$body = preg_replace('/\<!\-\-(\/)?FPC:'.$bodyTagQuoted.'\-\-\>/', '', $body);
			} else {
				$body = preg_replace('/\<!\-\-FPC:'.$bodyTagQuoted.'\-\-\>(.*)\<!\-\-\/FPC:'.$bodyTagQuoted.'\-\-\>/sU', '', $body);
			}
		}
		$page->setBody($body);
	}

	/**
	 * @param string $tag
	 * @param callable $contentCallback
	 */
	public function renderTag($tag, callable $contentCallback): void {
		$renderContent = $this->isCacheClient || $this->shouldRenderTag($tag);
		if($this->isCacheClient) {
			echo '<!--FPC:'.$tag.'-->';
		}
		if($renderContent) {
			call_user_func_array($contentCallback, [$this->isCacheClient]);
		}
		if($this->isCacheClient) {
			echo '<!--/FPC:'.$tag.'-->';
		}
	}

	/**
	 * registers a page to be handled by the cache
	 *
	 * @param string $pageKey
	 * @param int $refreshInterval
	 * @param array $responseHeaders
	 */
	public function registerPage($pageKey, $refreshInterval = null, array $responseHeaders = null): void {

		$refreshInterval = $refreshInterval ? $refreshInterval : $this->config->getDefaultRefreshInterval();
		$responseHeaders = $responseHeaders ? $responseHeaders : $this->config->getDefaultResponseHeaders();

		$this->backend->registerPage($this->request, $pageKey, $refreshInterval, $responseHeaders);
	}

	/**
	 * @return array
	 */
	public function getPagesToRefresh(): array {
		$expireInterval = $this->config->getExpireInterval();
		$pageList = $this->backend->getPagesToRefresh($expireInterval);
		return $pageList;
	}

	public function flush(): void {
		$this->backend->flush();
	}

	public function refreshAll(): void {
		$this->backend->refreshAll();
	}

	/**
	 * @return BackendStats|null
	 */
	public function getStats(): ?BackendStats {
		return $this->backend->getStats();
	}

    /**
     * @return Config
     */
    public function getConfig(): Config {
        return $this->config;
    }
}