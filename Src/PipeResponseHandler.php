<?php
/**
 * Server Request Handler that returns the middleware response hydrated by the pipe.
 *
 * @package TheWebSolver\Codegarage\Library
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class PipeResponseHandler implements RequestHandlerInterface {
	public function __construct( private readonly ResponseInterface $response ) {}

	public function handle( ServerRequestInterface $request ): ResponseInterface {
		return $this->response;
	}
}
