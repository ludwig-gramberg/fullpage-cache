<?php
namespace FullPageCache;

class BackendStats {

	/**
	 * @var int
	 */
	protected $numberOfPages;

	/**
	 * @var int
	 */
	protected $memoryBytes;

	/**
	 * Stats constructor.
	 *
	 * @param int $numberOfPages
	 * @param int $memoryBytes
	 */
	public function __construct(int $numberOfPages, int $memoryBytes) {
		$this->numberOfPages = $numberOfPages;
		$this->memoryBytes = $memoryBytes;
	}

	/**
	 * @return int
	 */
	public function getNumberOfPages(): int {
		return $this->numberOfPages;
	}

	/**
	 * @return int
	 */
	public function getMemoryBytes(): int {
		return $this->memoryBytes;
	}
}