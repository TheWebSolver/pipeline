<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Pipeline\Error;

use TypeError;

class InvalidMiddlewareForPipe extends TypeError {
	public static function from( mixed $middleware ): self {
		return new self(
			code: 400,
			message: ! is_string( $middleware )
				? 'Invalid middleware given: ' . get_debug_type( $middleware )
				: "Invalid middleware classname given: {$middleware}.",
		);
	}
}
