<?php
/**
 * Bridge for handling implementation of PSR7 and PSR15.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use Throwable;
use TypeError;
use LogicException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use TheWebSolver\Codegarage\Lib\PipeInterface as Pipe;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class PipelineBridge {
	public const MIDDLEWARE_RESPONSE = 'middlewareResponse';

	/**
	 * @param string|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed $from
	 * @throws InvalidPipeError When invalid pipe given.
	 */
	public static function toPipe( string|Closure|Pipe $from ): Pipe {
		return new class( $from ) implements Pipe {
			public function __construct( private readonly string|Closure|Pipe $pipe ) {}

			/**
			 * @param mixed         $subject
			 * @param Closure(mixed $subject, mixed ...$use): mixed $next
			 */
			public function handle( mixed $subject, Closure $next, mixed ...$use ): mixed {
				return $next( Pipeline::resolve( $this->pipe )( $subject, $next, ...$use ) );
			}
		};
	}

	/**
	 * @param string|Closure|Middleware $middleware
	 * @return Middleware
	 * @throws LogicException When invoked on projects that doesn't implement PSR7 & PSR15
	 * @throws TypeError When middleware creation fails due to invalid classname.
	 * @throws Throwable When unknown error occurs.
	 */
	public static function toMiddleware( $middleware ) {
		$interface = '\\Psr\\Http\\Server\\MiddlewareInterface';

		if ( ! interface_exists( $interface ) ) {
			throw new LogicException(
				'Project does not use implementation of PSR15 HTTP Server Middleware.'
			);
		}

		$provided    = $middleware;
		$isClassName = is_string( $middleware ) && class_exists( $middleware );

		try {
			$middleware = ( match ( true ) {
				// Middleware classname is a non-existing classname, then default to null.
				default                           => null,
				$middleware instanceof Closure    => $middleware,
				$isClassName                      => ( new $middleware() )->process( ... ),
				$middleware instanceof Middleware => $middleware->process( ... ),
			} );

			if ( null === $middleware ) {
				throw new TypeError(
					sprintf(
						'Non-existing class "%1$s". Middleware must be a Closure, an instance of %2$s'
						. ' or classname of a class that implements %2$s.',
						$provided,
						$interface
					)
				);
			}

			return new class( $middleware ) implements Middleware {
				public function __construct( private readonly Closure $middleware ) {}

				public function process( Request $request, Handler $handler ): Response {
					return ( $this->middleware )( $request, $handler );
				}
			};
		} catch ( Throwable $e ) {
			if ( ! is_string( $middleware ) ) {
				throw $e;
			}

			throw new TypeError(
				sprintf(
					'The given middleware classname: "%1$s" must be an instance of "%2$s".',
					$middleware,
					$interface
				)
			);
		}//end try
	}

	/** @param string|Closure|Middleware $middleware */
	public static function middlewareToPipe( $middleware ): Pipe {
		return self::toPipe(
			// Because Pipe::handle() wraps this function with the next pipe, we do not need to...
			static fn ( Response $r, Closure $next, Request $request, Handler $handler ) =>
				// ...manually wrap middleware with $next & let createPipe take care of it.
				// If we do so, same middleware will recreate response multiple times
				// making our app less performant which we don't want at all cost.
				self::toMiddleware( $middleware )
					->process( $request->withAttribute( self::MIDDLEWARE_RESPONSE, $r ), $handler )
		);
	}
}
