<?php
/**
 * The pipeline's pipe stub.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

class PipeStub implements PipeInterface {
	public function handle( mixed $subject, \Closure $next, mixed ...$use ): mixed {
		return $subject;
	}
}
