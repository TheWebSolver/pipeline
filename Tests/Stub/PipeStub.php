<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Stub;

use TheWebSolver\Codegarage\Lib\PipeInterface;

class PipeStub implements PipeInterface {
	public function handle( mixed $subject, \Closure $next, mixed ...$args ): mixed {
		return $subject;
	}
}
