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
use Psr\Http\Message\ServerRequestInterface as Request;
use TheWebSolver\Codegarage\Lib\Interfaces\PipeInterface;

class PipelineBridge {
	private Request $request;
	private Response $response;
	/** @var array<PipeInterface> */
	private array $pipes;

	public function __construct( private ?ContainerInterface $container = null ) {}

	public function withRequest( Request $request ): self {
		$this->request = $request;

		return $this;
	}

	public function using( ContainerInterface $container ): self {
		$this->container = $container;

		return $this;
	}

	public function process( Response $response ): self {
		$this->response = $response;

		return $this;
	}

	/** @param array<string|Closure|PipeInterface> $pipes */
	public function through( array $pipes ): self {
		foreach ( $pipes as $pipe ) {
			$this->pipes[] = Pipe::create( $pipe, $this->container );
		}

		return $this;
	}

	/** @param array<string|Closure|MiddlewareInterface> $middlewares */
	public function throughMiddlewares( array $middlewares ): self {
		$this->pipes = array_map( $this->middlewareToPipe( ... ), $middlewares );

		return $this;
	}

	public function getResponse(): Response {
		$transformed = ( new Pipeline( $this->container ) )
			->use( $this->request )
			->send( $this->response )
			->through( $this->pipes )
			->thenReturn();

		// Transformed value is always a Response. Making static analysis happy
		// and enforcing maximum security of the application along the way.
		return $transformed instanceof Response
			? $transformed
			: throw new LogicException(
				'Response instance must be returned. Instead returns: ' . get_debug_type( $transformed )
			);
	}

	/**
	 * Converts to pipe irrespective of middleware being invalid.
	 *
	 * Exception is only thrown when pipeline has started transforming the subject (Response).
	 */
	public static function middlewareToPipe(
		string|Closure|MiddlewareInterface $handler,
		?ContainerInterface $container = null
	): PipeInterface {
		return Middleware::toPipe( $handler, $container );
	}
}
