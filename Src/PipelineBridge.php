<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use LogicException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Pipe;
use Psr\Http\Server\MiddlewareInterface;
use TheWebSolver\Codegarage\Lib\Psr\Middleware;
use Psr\Http\Message\ResponseInterface as Response;
use TheWebSolver\Codegarage\Lib\Psr\RequestHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use TheWebSolver\Codegarage\Lib\Interfaces\PipeInterface;

class PipelineBridge {
	public function __construct( private readonly ?ContainerInterface $container = null ) {}

	/**
	 * Converts to pipe irrespective of middleware being invalid.
	 *
	 * Exception is only thrown when pipeline has started transforming the subject (Response).
	 */
	public function middlewareToPipe( string|Closure|MiddlewareInterface $handler ): PipeInterface {
		return Pipe::create(
			fn ( Response $subject, Closure $next, Request $request, mixed ...$args ) => $next(
				Middleware::create( $handler, $this->container )
					->process( $request, $this->withHandler( $subject, reset( $args ) ) )
			)
		);
	}

	private function withHandler( Response $response, mixed $arg ): Handler {
		$handler = is_string( $arg ) ? new $arg( $response ) : new RequestHandler( $response );

		return $handler instanceof Handler
			? $handler
			: throw new LogicException( 'Invalid Request Handler provided for pipeline: ' . $arg );
	}
}
