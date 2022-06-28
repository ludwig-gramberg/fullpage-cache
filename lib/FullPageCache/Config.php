<?php
namespace FullPageCache;

class Config {

	protected int $defaultRefreshInterval;

	/**
	 * how old can something get after it should have been refreshed before it is removed from redis
	 * so if the refresh worker was not working everything would be removed from redis (produce misses)
	 * after this time
	 */
	protected int $expireInterval;

	protected int $cacheClientFetchTimeout;

	protected bool $cacheClientIgnoreSslErrors = false;
    
    protected bool $canonicalHasTrailingSlash = false;

	protected array $defaultResponseHeaders = [];

	protected array $domains = [];

	protected array $schemes = [];

	protected array $stateTags = [];

	protected ?\Closure $useCacheCallback = null;

	protected ?\Closure $processTagsCallback = null;

	protected ?\Closure $postProcessCallback = null;

	public function __construct(array $domains, array $schemes, int $defaultRefreshInterval = 600, int $expireInterval = 600, int $cacheClientFetchTimeout = 30) {
		$this->domains = $domains;
		$this->schemes = $schemes;
		$this->defaultRefreshInterval = $defaultRefreshInterval;
		$this->expireInterval = $expireInterval;
		$this->cacheClientFetchTimeout = $cacheClientFetchTimeout;
	}

	public function isCacheClientIgnoreSslErrors(): bool {
		return $this->cacheClientIgnoreSslErrors;
	}

	public function setCacheClientIgnoreSslErrors(bool $cacheClientIgnoreSslErrors): void {
		$this->cacheClientIgnoreSslErrors = $cacheClientIgnoreSslErrors;
	}

    public function isCanonicalHasTrailingSlash(): bool {
        return $this->canonicalHasTrailingSlash;
    }

    public function setCanonicalHasTrailingSlash(bool $canonicalHasTrailingSlash): void {
        $this->canonicalHasTrailingSlash = $canonicalHasTrailingSlash;
    }

	public function getDomains(): array {
		return $this->domains;
	}

	public function setDomains(array $domains): void {
		$this->domains = $domains;
	}

	public function getSchemes(): array {
		return $this->schemes;
	}

	public function setSchemes(array $schemes): void {
		$this->schemes = $schemes;
	}

	public function getStateTags(): array {
		return $this->stateTags;
	}

	public function setStateTags(array $stateTags): void {
		$this->stateTags = $stateTags;
	}

	public function addStateTag(string $stateTag): void {
		$this->stateTags[] = $stateTag;
	}

	public function setUseCacheCallback(callable $useCacheCallback): void {
		$this->useCacheCallback = $useCacheCallback;
	}

	public function hasUseCacheCallback(): bool {
		return is_callable($this->useCacheCallback);
	}

	public function getUseCacheCallback(): callable {
		return $this->useCacheCallback;
	}

	public function getProcessTagsCallback(): callable {
		return $this->processTagsCallback;
	}

	public function hasProcessTagsCallback(): bool {
		return is_callable($this->processTagsCallback);
	}

	public function setProcessTagsCallback(callable $processTagsCallback): void {
		$this->processTagsCallback = $processTagsCallback;
	}

	public function getPostProcessCallback(): callable {
		return $this->postProcessCallback;
	}

	public function hasPostProcessCallback(): bool {
		return is_callable($this->postProcessCallback);
	}

	public function setPostProcessCallback(callable $postProcessCallback): void {
		$this->postProcessCallback = $postProcessCallback;
	}

	public function getDefaultRefreshInterval(): int {
		return $this->defaultRefreshInterval;
	}

	public function setDefaultResponseHeaders(array $defaultResponseHeaders): void {
		$this->defaultResponseHeaders = $defaultResponseHeaders;
	}

	public function getDefaultResponseHeaders(): array {
		return $this->defaultResponseHeaders;
	}

	public function getExpireInterval(): int {
		return $this->expireInterval;
	}

	public function getCacheClientFetchTimeout(): int {
		return $this->cacheClientFetchTimeout;
	}

}