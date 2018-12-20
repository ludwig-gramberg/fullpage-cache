<?php
namespace FullPageCache;

class Config {

	/**
	 * @var int
	 */
	protected $defaultRefreshInterval;

	/**
	 * how old can something get after it should have been refreshed before it is removed from redis
	 * so if the refresh worker was not working everything would be removed from redis (produce misses)
	 * after this time
	 *
	 * @var int
	 */
	protected $expireInterval;

	/**
	 * @var int
	 */
	protected $cacheClientFetchTimeout;

	/**
	 * @var bool
	 */
	protected $cacheClientIgnoreSslErrors = false;

	/**
	 * @var array
	 */
	protected $defaultResponseHeaders = array();

	/**
	 * @var array
	 */
	protected $domains = array();

	/**
	 * @var array
	 */
	protected $schemes = array();

	/**
	 * @var array
	 */
	protected $stateTags = array();

	/**
	 * @var callable
	 */
	protected $useCacheCallback;

	/**
	 * @var callable
	 */
	protected $processTagsCallback;

	/**
	 * @var callable
	 */
	protected $postProcessCallback;

	/**
	 * Config constructor.
	 *
	 * @param array $domains
	 * @param array $schemes
	 * @param int $defaultRefreshInterval
	 * @param int $expireInterval
	 * @param int $cacheClientFetchTimeout
	 */
	public function __construct(array $domains, array $schemes, $defaultRefreshInterval = 600, $expireInterval = 600, $cacheClientFetchTimeout = 30) {
		$this->domains = $domains;
		$this->schemes = $schemes;
		$this->defaultRefreshInterval = $defaultRefreshInterval;
		$this->expireInterval = $expireInterval;
		$this->cacheClientFetchTimeout = $cacheClientFetchTimeout;
	}

	/**
	 * @return bool
	 */
	public function isCacheClientIgnoreSslErrors(): bool {
		return $this->cacheClientIgnoreSslErrors;
	}

	/**
	 * @param bool $cacheClientIgnoreSslErrors
	 */
	public function setCacheClientIgnoreSslErrors(bool $cacheClientIgnoreSslErrors): void {
		$this->cacheClientIgnoreSslErrors = $cacheClientIgnoreSslErrors;
	}

	/**
	 * @return array
	 */
	public function getDomains(): array {
		return $this->domains;
	}

	/**
	 * @param array $domains
	 */
	public function setDomains(array $domains): void {
		$this->domains = $domains;
	}

	/**
	 * @return array
	 */
	public function getSchemes(): array {
		return $this->schemes;
	}

	/**
	 * @param array $schemes
	 */
	public function setSchemes(array $schemes): void {
		$this->schemes = $schemes;
	}

	/**
	 * @return array
	 */
	public function getStateTags(): array {
		return $this->stateTags;
	}

	/**
	 * @param array $stateTags
	 */
	public function setStateTags(array $stateTags): void {
		$this->stateTags = $stateTags;
	}

	/**
	 * @param string $stateTag
	 */
	public function addStateTag($stateTag): void {
		$this->stateTags[] = $stateTag;
	}

	/**
	 * @param callable $useCacheCallback
	 */
	public function setUseCacheCallback(callable $useCacheCallback): void {
		$this->useCacheCallback = $useCacheCallback;
	}

	/**
	 * @return bool
	 */
	public function hasUseCacheCallback(): bool {
		return is_callable($this->useCacheCallback);
	}

	/**
	 * @return callable
	 */
	public function getUseCacheCallback(): callable {
		return $this->useCacheCallback;
	}

	/**
	 * @return callable
	 */
	public function getProcessTagsCallback(): callable {
		return $this->processTagsCallback;
	}

	/**
	 * @return bool
	 */
	public function hasProcessTagsCallback(): bool {
		return is_callable($this->processTagsCallback);
	}

	/**
	 * @param callable $processTagsCallback
	 */
	public function setProcessTagsCallback(callable $processTagsCallback): void {
		$this->processTagsCallback = $processTagsCallback;
	}

	/**
	 * @return callable
	 */
	public function getPostProcessCallback(): callable {
		return $this->postProcessCallback;
	}

	/**
	 * @return bool
	 */
	public function hasPostProcessCallback(): bool {
		return is_callable($this->postProcessCallback);
	}

	/**
	 * @param callable $postProcessCallback
	 */
	public function setPostProcessCallback(callable $postProcessCallback): void {
		$this->postProcessCallback = $postProcessCallback;
	}

	/**
	 * @return int
	 */
	public function getDefaultRefreshInterval(): int {
		return $this->defaultRefreshInterval;
	}

	/**
	 * @param array $defaultResponseHeaders
	 */
	public function setDefaultResponseHeaders(array $defaultResponseHeaders): void {
		$this->defaultResponseHeaders = $defaultResponseHeaders;
	}

	/**
	 * @return array
	 */
	public function getDefaultResponseHeaders(): array {
		return $this->defaultResponseHeaders;
	}

	/**
	 * @return int
	 */
	public function getExpireInterval(): int {
		return $this->expireInterval;
	}

	/**
	 * @return int
	 */
	public function getCacheClientFetchTimeout(): int {
		return $this->cacheClientFetchTimeout;
	}

}