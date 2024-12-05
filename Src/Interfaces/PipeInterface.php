<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Pipeline\Interfaces;

use Closure;

interface PipeInterface {
	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
	/**
	 * Handles the given subject and returns the transformed data.
	 *
	 * @param mixed         $subject The subject to be transformed by the pipe.
	 * @param Closure(mixed $subject, mixed ...$args): mixed $next
	 * @param mixed         ...$args  The global args that may or may not be in use
	 *                               for the current pipeline.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function handle( mixed $subject, Closure $next, mixed ...$args ): mixed;
}
