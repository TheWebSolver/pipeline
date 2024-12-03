<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Psr;

use Closure;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class Middleware implements MiddlewareInterface {
	/** @param Closure(ServerRequestInterface, RequestHandlerInterface): ResponseInterface $middleware */
	// phpcs:ignore Squiz.Commenting.FunctionComment.IncorrectTypeHint
	public function __construct( private readonly Closure $middleware ) {}

	public function process( ServerRequestInterface $request, RequestHandlerInterface $handler ): ResponseInterface {
		return ( $this->middleware )( $request, $handler );
	}
}
