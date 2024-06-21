<?php
/**
 * Unknown pipeline exception.
 *
 * @package TheWebSolver\Codegarage\Library
 */

namespace TheWebSolver\Codegarage\Lib;

use Exception;
use Throwable;

class InvalidPipeline extends Exception {
	public function __construct( Throwable $previous, private readonly mixed $subject = null ) {
		parent::__construct( $previous->getMessage(), $previous->getCode(), $previous );
	}

	public function hasSubject(): bool {
		return isset( $this->subject );
	}

	public function getSubject(): mixed {
		return $this->subject;
	}
}
