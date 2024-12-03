<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use Throwable;
use LogicException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Pipe;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheWebSolver\Codegarage\Lib\PipeInterface;
use TheWebSolver\Codegarage\Lib\Psr\Middleware;

class PipelineBridge {
	public function __construct( private readonly ?ContainerInterface $container = null ) {}

	/** @throws InvalidMiddlewareForPipe When middleware creation fails due to invalid classname. */
	public function toMiddleware( string|Closure|MiddlewareInterface $middleware ): MiddlewareInterface {
		try {
			return match ( true ) {
				$middleware instanceof MiddlewareInterface => $middleware,
				$middleware instanceof Closure             => new Middleware( $middleware ),
				default                                    => $this->container?->get( $middleware ) ?? new $middleware()
			};
		} catch ( Throwable $e ) {
			throw new InvalidMiddlewareForPipe( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Converts to pipe irrespective of middleware being invalid.
	 *
	 * Exception is only thrown when converted pipe is invoked and when subject is transformed.
	 */
	public function middlewareToPipe( string|Closure|MiddlewareInterface $middleware ): PipeInterface {
		return Pipe::create(
			fn ( ResponseInterface $response, Closure $next, ServerRequestInterface $request, mixed ...$args ) => $next(
				$this->toMiddleware( $middleware )->process( $request, $this->withHandler( $response, reset( $args ) ) )
			)
		);
	}

	private function withHandler( ResponseInterface $response, mixed $arg ): RequestHandlerInterface {
		if ( ! is_string( $arg ) ) {
			return new PipeResponseHandler( $response );
		}

		return is_a( $arg, RequestHandlerInterface::class, allow_string: true )
			? new $arg( $response )
			: throw new LogicException( 'Invalid Request Handler provided for pipeline usage.' );
	}
}
