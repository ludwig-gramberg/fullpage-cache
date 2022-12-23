<?php
namespace FullPageCache;

class Page {

	protected string $key;

	protected string $body;

	protected array $headers = [];

	public function __construct(string $key, string $body, array $headers) {
		$this->key     = $key;
		$this->body    = $body;
		$this->headers = $headers;
	}

	public function getKey(): string {
		return $this->key;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function setBody(string $body): void {
		$this->body = $body;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function setHeaders(array $headers): void {
		$this->headers = $headers;
	}

	public function sendResponse(): void {
		header('X-FPC-Key:'.$this->key);
		foreach($this->headers as $header) {
			header($header);
		}
		echo $this->body;
	}
    
}