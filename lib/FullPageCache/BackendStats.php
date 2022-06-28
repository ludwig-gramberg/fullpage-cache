<?php
namespace FullPageCache;

class BackendStats {

	protected int $numberOfPages;

	protected int $memoryBytes;

	public function __construct(int $numberOfPages, int $memoryBytes) {
		$this->numberOfPages = $numberOfPages;
		$this->memoryBytes = $memoryBytes;
	}

	public function getNumberOfPages(): int {
		return $this->numberOfPages;
	}

	public function getMemoryBytes(): int {
		return $this->memoryBytes;
	}
}