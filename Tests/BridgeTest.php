<?php
/**
 * Pipeline bridge test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use LogicException;
use MiddlewareAdapter;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\PipelineBridge;
use TheWebSolver\Codegarage\Lib\Psr7Adapter\Request;
use TheWebSolver\Codegarage\Lib\Psr7Adapter\Response;
use TheWebSolver\Codegarage\Lib\Psr15Adapter\Middleware;
use TheWebSolver\Codegarage\Lib\Psr7Adapter\ResponseInterface;
use TheWebSolver\Codegarage\Lib\Psr15Adapter\MiddlewareInterface;
use TheWebSolver\Codegarage\Lib\Psr7Adapter\ServerRequestInterface;
use TheWebSolver\Codegarage\Lib\Psr15Adapter\RequestHandlerInterface;

class BridgeTest extends TestCase {
	protected function setUp(): void {
		require_once __DIR__ . '/Stub/PsrStubs.php';
	}

	public function testPSRBridge() {
		PipelineBridge::setMiddlewareAdapter(
			interface: MiddlewareInterface::class,
			className: MiddlewareAdapter::class
		);

		$request = new Request();
		$handler = new class() implements RequestHandlerInterface {
			public function handle( ServerRequestInterface $request ): ResponseInterface {
				$middlewares[] = Middleware::class;
				$middlewares[] = static fn ( ServerRequestInterface $request, RequestHandlerInterface $h )
					=> $request
						->getAttribute( PipelineBridge::MIDDLEWARE_RESPONSE )
						->withStatus( code: 300 );

				$middlewares[] = new class() implements MiddlewareInterface {
					public function process(
						ServerRequestInterface $request,
						RequestHandlerInterface $handler
					): ResponseInterface {
						$response = $request
						->getAttribute( PipelineBridge::MIDDLEWARE_RESPONSE )
						->withStatus( code: 350 );

						return $response;
					}
				};

				return ( new Pipeline() )
					->use( $request, $this )
					->send( subject: ( new Response() )->withStatus( code: 100 ) )
					->through(
						pipes: array_map( fn( $m ) => PipelineBridge::middlewareToPipe( $m ), $middlewares )
					)->thenReturn();
			}
		};

		$this->assertSame( expected: 350, actual: $handler->handle( $request )->getStatusCode() );

		PipelineBridge::resetMiddlewareAdapter();

		// Must always throw exception if core PSR-15 implementation not used.
		if ( ! interface_exists( '\\Psr\\Http\\Server\\MiddlewareInterface' ) ) {
			$this->expectException( LogicException::class );
			$this->expectExceptionMessage( 'Cannot find implementation of PSR15 HTTP Server Middleware.' );

			PipelineBridge::toMiddleware( middleware: '' );
		}
	}
}
