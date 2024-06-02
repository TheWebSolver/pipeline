<?php
/**
 * Exception when invalid pipe given.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use TypeError;

class InvalidPipeError extends TypeError {
	public static function from( mixed $pipe ): self {
		return new self( $pipe );
	}

	private function __construct( mixed $pipe ) {
		parent::__construct(
			message: ! is_string( $pipe ) ? '' : "Invalid pipe classname given: {$pipe}.",
			code: 400
		);
	}
}
