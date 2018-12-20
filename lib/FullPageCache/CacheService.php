<?php
namespace FullPageCache;

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
	protected $renderTags = array();

    /**
     * CacheService constructor.
     * @param Config $config
     * @param Backend $backend
     * @param Request $request
     */
	protected function __construct(Config $config, Backend $backend, Request $request) {
	    $this->request = $request;
		$this->config = $config;
		$this->backend = $backend;
		if($this->config->hasProcessTagsCallback()) {
			$this->renderTags = call_user_func($this->config->getProcessTagsCallback());
		}
		$this->isCacheClient = $this->request->getUserAgent() == CacheRefreshWorker::USER_AGENT;
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
		if(!in_array($this->request->getDomain(), $allowedHostNames)) {
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
	 * @param Page $page
	 */
	protected function renderTags(Page $page): void {
		$body = $page->getBody();
		preg_match_all('/\<!\-\-FPC:(.+)\-\-\>/U', $body, $m);
		foreach($m[1] as $bodyTag) {
			$bodyTagQuoted = preg_quote($bodyTag);
			if(in_array($bodyTag, $this->renderTags)) {
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
		$renderTags = $this->isCacheClient;
		$renderContent = $renderTags || in_array($tag, $this->renderTags);
		if($renderTags) {
			echo '<!--FPC:'.$tag.'-->';
		}
		if($renderContent) {
			call_user_func($contentCallback);
		}
		if($renderTags) {
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
}