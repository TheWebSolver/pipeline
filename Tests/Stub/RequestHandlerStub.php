<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Stub;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheWebSolver\Codegarage\Lib\PipelineBridge;
use TheWebSolver\Codegarage\Lib\Interfaces\ChainOfResponsibility;

class RequestHandlerStub implements RequestHandlerInterface {
	public function __construct(
		private readonly ChainOfResponsibility|PipelineBridge $handler,
		private readonly PipelineBridge $bridge = new PipelineBridge()
	) {}

	public function handle( ServerRequestInterface $request ): ResponseInterface {
		return $this->handler instanceof PipelineBridge ? $this->handler->getResponse() : $this->handler->thenReturn();
	}
}
