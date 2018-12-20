<?php
namespace FullPageCache;

class Page {

	/**
	 * @var string
	 */
	protected $key;

	/**
	 * @var string
	 */
	protected $body;

	/**
	 * @var array
	 */
	protected $headers = array();

	/**
	 * @var int
	 */
	protected $expireTime;

	/**
	 * @var int
	 */
	protected $refreshInterval;

	/**
	 * Page constructor.
	 *
	 * @param string $key
	 * @param string $body
	 * @param array $headers
	 */
	public function __construct(string $key, string $body, array $headers) {
		$this->key             = $key;
		$this->body            = $body;
		$this->headers         = $headers;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return $this->key;
	}

	/**
	 * @return string
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * @param string $body
	 */
	public function setBody(string $body): void {
		$this->body = $body;
	}

	/**
	 * @return array
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * @param array $headers
	 */
	public function setHeaders(array $headers): void {
		$this->headers = $headers;
	}

    /**
     *
     */
	public function sendResponse(): void {
		header('X-FPC-Key:'.$this->key, true);
		foreach($this->headers as $header) {
			header($header, true);
		}
		echo $this->body;
	}
}