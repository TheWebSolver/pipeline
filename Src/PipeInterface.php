<?php
/**
 * The pipeline handler to handle given subject/request.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;

interface PipeInterface {
	/**
	 * Handles the given subject and returns the transformed data.
	 *
	 * @param mixed         $subject The subject to be transformed by the pipe.
	 * @param Closure(mixed $subject, mixed ...$use): mixed $next
	 * @param mixed         ...$use  The global args that may or may not be in use
	 *                               for the current pipeline.
	 * @since 1.0
	 */
	public function handle( mixed $subject, Closure $next, mixed ...$use ): mixed;
}
