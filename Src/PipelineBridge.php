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
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface as Middleware;
use TheWebSolver\Codegarage\Lib\PipeInterface as Pipe;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;

class PipelineBridge {
	/** @phpstan-param class-string */
	private static string $middlewareInterface;
	/** @phpstan-param class-string */
	private static string $middlewareClass;
	/** @var \Psr\Container\ContainerInterface */
	private static object $container;

	// phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch -- Closure param OK.
	/**
	 * @param string|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed $from
	 * @throws InvalidPipeError            When invalid pipe given.
	 * @throws UnexpectedPipelineException When could not determine thrown exception.
	 */
	public static function toPipe( string|Closure|Pipe $from ): Pipe {
		$pipe = Pipeline::resolve( pipe: $from );
		return new class( $pipe ) implements Pipe {
			public function __construct( private readonly Closure $pipe ) {}

			public function handle( mixed $subject, Closure $next, mixed ...$use ): mixed {
				return $next( ( $this->pipe )( $subject, $next, ...$use ) );
			}
		};
	}
	// phpcs:enable

	/**
	 * @throws MiddlewarePsrNotFoundException When invoked on projects that doesn't implement PSR7 & PSR15.
	 * @throws InvalidMiddlewareForPipeError  When middleware creation fails due to invalid classname.
	 * @throws UnexpectedPipelineException    When could not determine thrown exception.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Exactly 3 exceptions thrown.
	public static function toMiddleware( mixed $middleware ): object {
		$interface = static::hasMiddlewareInterfaceAdapter()
			? static::$middlewareInterface
			: (
				! interface_exists( $default = '\\Psr\\Http\\Server\\MiddlewareInterface' )
					? throw new MiddlewarePsrNotFoundException( 'Cannot find PSR15 HTTP Server Middleware.' )
					: $default
			);

		$isClassName = is_string( $middleware ) && class_exists( $middleware );

		try {
			$middleware = match ( true ) {
				// Middleware classname is a non-existing classname, then default to null.
				default                         => null,
				$middleware instanceof Closure  => $middleware,
				$isClassName                    => static::make( $middleware )->process( ... ),
				is_a( $middleware, $interface ) => $middleware->process( ... ),
			};

			if ( null !== $middleware ) {
				return static::getMiddlewareAdapter( $middleware );
			}

			throw new InvalidMiddlewareForPipeError(
				sprintf(
					'Invalid middleware type. Middleware must be a Closure, an instance of'
					. ' "%1$s" or a concrete\'s classname that implements "%1$s".',
					$interface
				)
			);
		} catch ( Throwable $e ) {
			if ( $e instanceof InvalidMiddlewareForPipeError ) {
				throw $e;
			}

			if ( ! is_string( $middleware ) ) {
				throw new UnexpectedPipelineException( $e->getMessage(), $e->getCode(), $e );
			}

			throw new InvalidMiddlewareForPipeError(
				sprintf(
					'The given middleware classname: "%1$s" must be an instance of "%2$s".',
					$middleware,
					$interface
				)
			);
		}//end try
	}

	public static function middlewareToPipe( mixed $middleware ): Pipe {
		return static::toPipe(
			from: static fn ( $response, $next, $request, ...$use ) => static::toMiddleware( $middleware )
				->process( $request, handler: static::getHandlerAdapter( with: $response, args: $use ) )
		);
	}

	/**
	 * @param \Psr\Http\Message\ResponseInterface $with
	 * @param array<mixed>                        $args Should only be a handler class for mocking.
	 * @return \Psr\Http\Server\RequestHandlerInterface
	 */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Contravariance.
	protected static function getHandlerAdapter( object $with, array $args ) {
		$handler = $args[0] ?? false;

		return $handler ? new $handler( $with ) : new class( $with ) implements Handler {
			public function __construct( private readonly Response $response ) {}

			public function handle( Request $request ): Response {
				return $this->response;
			}
		};
	}

	public static function make( string $className ): object {
		return isset( static::$container ) && static::$container->has( id: $className )
			? static::$container->get( id: $className )
			: new $className();
	}

	/** @param \Psr\Container\ContainerInterface $container */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Contravariance.
	public static function setApp( object $container ): void {
		static::$container = $container;
	}

	public static function setMiddlewareAdapter( string $interface, string $className ): void {
		static::$middlewareInterface = $interface;
		static::$middlewareClass     = $className;
	}

	public static function resetMiddlewareAdapter(): void {
		static::$middlewareInterface = '';
		static::$middlewareClass     = '';
	}

	protected static function getMiddlewareAdapter( Closure $middleware ): object {
		return static::hasMiddlewareClassAdapter()
			? new static::$middlewareClass( $middleware )
			: new class( $middleware ) implements Middleware {
				public function __construct( private readonly Closure $middleware ) {}

				public function process( Request $request, Handler $handler ): Response {
					return ( $this->middleware )( $request, $handler );
				}
			};
	}

	protected static function hasMiddlewareInterfaceAdapter(): bool {
		return isset( static::$middlewareInterface ) && interface_exists( static::$middlewareInterface );
	}

	protected static function hasMiddlewareClassAdapter(): bool {
		return isset( static::$middlewareClass ) && class_exists( static::$middlewareClass );
	}
}
