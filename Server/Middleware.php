<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Pipeline;

use Closure;
use Throwable;
use LogicException;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Pipeline\Pipe;
use Psr\Http\Server\MiddlewareInterface;
use TheWebSolver\Codegarage\Pipeline\Resolver;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as Handler;
use TheWebSolver\Codegarage\Pipeline\Interfaces\PipeInterface;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidMiddlewareForPipe;

class Middleware implements MiddlewareInterface {
	/** @use Resolver<MiddlewareInterface> */
	use Resolver;

	private const RESOLVER_TYPES = array( MiddlewareInterface::class, InvalidMiddlewareForPipe::class );

	/** @param Closure(Request, Handler): Response $middleware */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly Closure $middleware ) {}

	public function process( Request $request, Handler $handler ): Response {
		return ( $this->middleware )( $request, $handler );
	}

	/** @throws InvalidMiddlewareForPipe When resolving middleware fails. */
	public static function create(
		string|Closure|MiddlewareInterface $handler,
		?ContainerInterface $container = null
	): MiddlewareInterface {
		try {
			return self::resolve( $handler, $container, self::RESOLVER_TYPES );
		} catch ( Throwable $e ) {
			throw new InvalidMiddlewareForPipe( $e->getMessage(), $e->getCode(), $e );
		}
	}

	public static function toPipe(
		string|Closure|MiddlewareInterface $handler,
		?ContainerInterface $container = null
	): PipeInterface {
		return Pipe::create(
			static fn ( Response $subject, Closure $next, Request $request, mixed ...$args ) => $next(
				self::create( $handler, $container )
					->process( $request, self::withHandler( $subject, reset( $args ) ) )
			)
		);
	}

	private static function withHandler( Response $response, mixed $arg ): Handler {
		$handler = is_string( $arg ) ? new $arg( $response ) : new RequestHandler( $response );

		return $handler instanceof Handler
			? $handler
			: throw new LogicException( 'Invalid Request Handler provided for pipeline: ' . $arg );
	}
}
