<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Psr;

use Closure;
use Throwable;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use TheWebSolver\Codegarage\Lib\Resolver;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheWebSolver\Codegarage\Lib\Error\InvalidMiddlewareForPipe;

class Middleware implements MiddlewareInterface {
	/** @use Resolver<MiddlewareInterface> */
	use Resolver;

	private const RESOLVER_TYPES = array( MiddlewareInterface::class, InvalidMiddlewareForPipe::class );

	/** @param Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $middleware */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly Closure $middleware ) {}

	public function process( ServerRequestInterface $request, RequestHandlerInterface $handler ): ResponseInterface {
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
}
