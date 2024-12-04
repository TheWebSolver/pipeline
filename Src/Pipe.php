<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use Throwable;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Error\InvalidPipe;
use TheWebSolver\Codegarage\Lib\Interfaces\PipeInterface;

class Pipe implements PipeInterface {
	/** @param Closure(mixed $subject, Closure $next, mixed ...$args): mixed $handler */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly Closure $handler ) {}

	public function handle( mixed $subject, Closure $next, mixed ...$args ): mixed {
		return ( $this->handler )( $subject, $next, ...$args );
	}

	public static function create(
		string|Closure|PipeInterface $handler,
		?ContainerInterface $container = null
	): PipeInterface {
		try {
			$pipe = ! is_string( $handler ) ? $handler : ( $container?->get( $handler ) ?? new $handler() );

			return match ( true ) {
				$pipe instanceof PipeInterface => $pipe,
				$pipe instanceof Closure       => new self( $pipe ),
				default                        => throw InvalidPipe::from( $handler ),
			};
		} catch ( Throwable $thrown ) {
			throw Pipeline::normalizeException( $thrown );
		}
	}
}
