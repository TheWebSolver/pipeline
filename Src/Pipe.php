<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Pipeline;

use Closure;
use Throwable;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidPipe;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidPipeline;
use TheWebSolver\Codegarage\Pipeline\Interfaces\PipeInterface;

class Pipe implements PipeInterface {
	/** @use Resolver<PipeInterface> */
	use Resolver;

	/** @param Closure(mixed $subject, Closure $next, mixed ...$args): mixed $handler */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly Closure $handler ) {}

	public function handle( mixed $subject, Closure $next, mixed ...$args ): mixed {
		return ( $this->handler )( $subject, $next, ...$args );
	}

	/**
	 * @throws InvalidPipe     When resolving pipe fails.
	 * @throws InvalidPipeline When exceptions other than `InvalidPipe` is thrown.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Actual number is vague.
	public static function create(
		string|Closure|PipeInterface $handler,
		?ContainerInterface $container = null
	): PipeInterface {
		try {
			return self::resolve( $handler, $container, array( PipeInterface::class, InvalidPipe::class ) );
		} catch ( Throwable $thrown ) {
			throw Pipeline::normalizeException( $thrown );
		}
	}
}
