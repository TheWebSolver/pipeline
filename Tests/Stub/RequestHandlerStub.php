<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Stub;

use Psr\Http\Message\ResponseInterface;
use TheWebSolver\Codegarage\Lib\Pipeline;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheWebSolver\Codegarage\Pipeline\Server\Bridge;
use TheWebSolver\Codegarage\Lib\Interfaces\ChainOfResponsibility;

class RequestHandlerStub implements RequestHandlerInterface {
	public function __construct( private readonly ChainOfResponsibility|Bridge $handler ) {}

	public function handle( ServerRequestInterface $request ): ResponseInterface {
		return $this->handler instanceof Pipeline ? $this->handler->thenReturn() : $this->handler->get();
	}
}
