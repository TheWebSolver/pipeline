<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Error;

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
