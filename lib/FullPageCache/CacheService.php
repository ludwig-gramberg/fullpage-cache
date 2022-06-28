<?php
namespace FullPageCache;

use Webframework\Request\Request;

class CacheService {

	protected Config $config;

	protected Backend $backend;

	protected Request $request;

	protected bool $isCacheClient;

	protected ?array $renderTags = null;

	public function __construct(Config $config, Backend $backend, Request $request) {
	    $this->request = $request;
		$this->config = $config;
		$this->backend = $backend;
		$this->isCacheClient = $this->request->getUserAgent() == CacheWorker::USER_AGENT;
	}

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

	protected function shouldRenderTag(string $tag): bool {
	    if($this->renderTags === null) {
            $this->renderTags = $this->config->hasProcessTagsCallback()
                ? call_user_func($this->config->getProcessTagsCallback())
                : [];
        }
        return in_array($tag, $this->renderTags);
    }

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

	public function renderTag(string $tag, callable $contentCallback): void {
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
	 */
	public function registerPage(string $pageKey, int $refreshInterval = null, array $responseHeaders = null): void {
        if(!in_array($this->request->getHost(), $this->config->getDomains())) {
            return;
        }
        if(!in_array($this->request->getScheme(), $this->config->getSchemes())) {
            return;
        }
	    
		$refreshInterval = $refreshInterval ?: $this->config->getDefaultRefreshInterval();
		$responseHeaders = $responseHeaders ?: $this->config->getDefaultResponseHeaders();

		$this->backend->registerPage($this->request, $pageKey, $refreshInterval, $responseHeaders, $this->config->isCanonicalHasTrailingSlash());
	}

	public function getPagesToRefresh(): array {
		return $this->backend->getPagesToRefresh();
	}

	public function flush(): void {
		$this->backend->flush();
	}

	public function refreshAll(): void {
		$this->backend->refreshAll();
	}

	public function getStats(): ?BackendStats {
		return $this->backend->getStats();
	}

    public function getConfig(): Config {
        return $this->config;
    }

    public function isCacheClient(): bool {
        return $this->isCacheClient;
    }
}