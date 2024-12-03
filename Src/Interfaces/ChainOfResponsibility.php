<?php // phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch, Squiz.Commenting.FunctionComment.IncorrectTypeHint
declare(strict_types = 1);

namespace TheWebSolver\Codegarage\Lib\Interfaces;

use Closure;
use Throwable;
use TheWebSolver\Codegarage\Lib\Error\InvalidPipe;
use TheWebSolver\Codegarage\Lib\Error\InvalidPipeline;
use TheWebSolver\Codegarage\Lib\Interfaces\PipeInterface as Handler;

interface ChainOfResponsibility {
	/**
	 * Provides additional arguments that can be used by all registered pipes.
	 */
	public function use( mixed ...$globalArgsForEachPipe ): static;

	/**
	 * Provides subject to be transformed by all registered pipes.
	 */
	public function send( mixed $subject ): static;

	/**
	 * Registers a single pipe to transform the subject.
	 *
	 * Pipes registered using this method before `ChainOfResponsibility::through()` method
	 * must be deferred and must transform the subject only after pipes registered using
	 * `ChainOfResponsibility::through()` method has transformed the subject, if any.
	 *
	 * @param class-string<Handler>|Handler|Closure(mixed $subject, Closure $next, mixed ...$args): mixed $handler
	 */
	public function pipe( string|Closure|Handler $handler ): static;

	/**
	 * Registers pipes to transform the subject.
	 *
	 * Subject must be transformed in the same order pipes are registered.
	 *
	 * @param array<class-string<Handler>|Handler|Closure(mixed $subject, Closure $next, mixed ...$args): mixed> $pipes
	 */
	public function through( array $pipes ): static;

	/**
	 * Catches any exception thrown during transformation of subject and returns the fallback value.
	 *
	 * @param Closure(Throwable $exception, mixed ...$args): mixed $fallback
	 */
	public function sealWith( Closure $fallback ): static;

	/**
	 * Returns the transformed subject after passing through all registered pipes and current pipe handle.
	 *
	 * @param Closure(mixed $subject, mixed ...$args): mixed $handle
	 * @throws InvalidPipe            When pipe type could not be resolved.
	 * @throws InvalidPipeline When a pipe abrupt the pipeline by throwing an exception & sealWith not used.
	 */
	public function then( Closure $handle ): mixed;

	/**
	 * Returns the transformed subject after passing through all registered pipes.
	 *
	 * @throws InvalidPipe     When pipe type could not be resolved.
	 * @throws InvalidPipeline When a pipe abrupt the pipeline by throwing an exception & sealWith not used.
	 */
	public function thenReturn(): mixed;
}
