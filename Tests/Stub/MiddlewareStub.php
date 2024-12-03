<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareStub implements MiddlewareInterface {
	public function process(
		ServerRequestInterface $request,
		RequestHandlerInterface $handler
	): ResponseInterface {
		$response = $handler->handle( $request );

		return $response->withStatus( code: $response->getStatusCode() + 100 );
	}
}
