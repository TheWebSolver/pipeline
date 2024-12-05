<?php // phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Pipeline;

use Closure;
use LogicException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Pipeline\Pipe;
use Psr\Http\Server\MiddlewareInterface;
use TheWebSolver\Codegarage\Pipeline\Pipeline;
use TheWebSolver\Codegarage\Pipeline\Psr\Middleware;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidPipe;
use Psr\Http\Message\ResponseInterface as Response;
use TheWebSolver\Codegarage\Pipeline\Psr\RequestHandler;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidPipeline;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use TheWebSolver\Codegarage\Pipeline\Interfaces\PipeInterface;
use TheWebSolver\Codegarage\Pipeline\Interfaces\ChainOfResponsibility;

class Bridge {
	/** @var PipeInterface[] */
	private array $pipes;
	private Request $request;
	private Response $response;

	/** @param class-string<Handler> $requestHandlerClassName Accepts a Response instance via constructor. */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct(
		private readonly ?ContainerInterface $container = null,
		private readonly ?ChainOfResponsibility $pipeline = null,
		private readonly string $requestHandlerClassName = RequestHandler::class
	) {}

	public function for( Request $request, Response $response ): self {
		$this->request  = $request;
		$this->response = $response;

		return $this;
	}

	/**
	 * @param string|PipeInterface|(Closure(Response, Closure $next, Request, ?string $requestHandlerClassName): Response) $pipe
	 * @param string|PipeInterface|(Closure(Response, Closure $next, Request, ?string $requestHandlerClassName): Response) ...$pipes
	 */
	public function through( string|Closure|PipeInterface $pipe, string|Closure|PipeInterface ...$pipes ): self {
		foreach ( array( $pipe, ...$pipes ) as $handler ) {
			$this->pipes[] = Pipe::create( $handler, $this->container );
		}

		return $this;
	}

	/**
	 * @param string|MiddlewareInterface|(Closure(Request, Handler): Response) $middleware
	 * @param string|MiddlewareInterface|(Closure(Request, Handler): Response) ...$middlewares
	 */
	public function throughMiddlewares(
		string|Closure|MiddlewareInterface $middleware,
		string|Closure|MiddlewareInterface ...$middlewares
	): self {
		foreach ( array( $middleware, ...$middlewares ) as $handler ) {
			$this->pipes[] = Middleware::toPipe( $handler, $this->container );
		}

		return $this;
	}

	/**
	 * @throws InvalidPipe|InvalidPipeline When Pipe is invalid or other error occurs in the pipeline.
	 * @throws LogicException              When pipeline does not return a Response instance.
	 */
	//  phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Exact number is vague.
	public function get(): Response {
		$transformed = ( $this->pipeline ?? new Pipeline( $this->container ) )
			->use( $this->request, $this->requestHandlerClassName )
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
}
