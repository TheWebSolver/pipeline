<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Psr;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class RequestHandler implements RequestHandlerInterface {
	public function __construct( private readonly ResponseInterface $response ) {}

	public function handle( ServerRequestInterface $request ): ResponseInterface {
		return $this->response;
	}
}
