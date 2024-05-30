<?php
/**
 * Pipeline bridge test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use Closure;
use MiddlewareAdapter;
use Psr7Adapter\Request;
use Psr7Adapter\Response;
use Psr15Adapter\Middleware;
use PHPUnit\Framework\TestCase;
use Psr7Adapter\ResponseInterface;
use Psr15Adapter\MiddlewareInterface;
use Psr7Adapter\ServerRequestInterface;
use Psr15Adapter\RequestHandlerInterface;
use TheWebSolver\Codegarage\Lib\Pipeline;
use TheWebSolver\Codegarage\Stub\PipeStub;
use TheWebSolver\Codegarage\Lib\PipeInterface;
use TheWebSolver\Codegarage\Lib\PipelineBridge;
use TheWebSolver\Codegarage\Lib\InvalidMiddlewareForPipeError;
use TheWebSolver\Codegarage\Lib\MiddlewarePsrNotFoundException;

class BridgeTest extends TestCase {
	private function addPsrPackageFixtures(): void {
		PipelineBridge::setMiddlewareAdapter(
			interface: MiddlewareInterface::class,
			className: MiddlewareAdapter::class
		);
	}

	private function removePsrPackageFixtures(): void {
		PipelineBridge::setMiddlewareAdapter( interface: '', className: '' );
	}

	/** @dataProvider provideVariousPipes */
	public function testPipeConversion( string|Closure|PipeInterface $from ): void {
		$this->assertInstanceOf( PipeInterface::class, PipelineBridge::toPipe( $from ) );
	}

	/** @return array<mixed[]> */
	public function provideVariousPipes(): array {
		return array(
			array( fn( $subject, $next ) => $next( $subject ) ),
			array( PipeStub::class ),
			array(
				new class implements PipeInterface {
					public function handle( mixed $subject, Closure $next, mixed ...$use ): mixed {
						return $next( $subject );
					}
				}
			),
		);
	}

	/** @dataProvider provideMiddlewares */
	public function testMiddlewareConversion( mixed $middleware, ?string $thrown ): void {
		$this->addPsrPackageFixtures();

		if ( $thrown ) {
			$this->expectException( $thrown );
		}

		$this->assertInstanceOf( MiddlewareInterface::class, PipelineBridge::toMiddleware( $middleware ) );
	}

	/** @dataProvider provideMiddlewares */
	public function testMiddlewareToPipeConversion( mixed $middleware, ?string $thrown ): void {
		// The middleware gets converted to pipe irrespective of middleware being invalid.
		// The exception is only thrown when converted pipe handles the subject.
		$this->assertInstanceOf( PipeInterface::class, PipelineBridge::middlewareToPipe( $middleware ) );
	}

	/** @return array<mixed[]>*/
	public function provideMiddlewares(): array {
		return array(
			array( Middleware::class, null ),
			array( $this->createMock( MiddlewareInterface::class ), null ),
			array( '\\Invalid\\Middleware', InvalidMiddlewareForPipeError::class ),
			array( true, InvalidMiddlewareForPipeError::class ),
			array( static::class, InvalidMiddlewareForPipeError::class ),
			array(
				static fn( ServerRequestInterface $r, RequestHandlerInterface $h ) => new Response(),
				null,
			),
			array(
				new class implements MiddlewareInterface {
					public function process(
						ServerRequestInterface $request,
						RequestHandlerInterface $handler
					): ResponseInterface {
						return new Response();
					}
				},
				null,
			),
		);
	}

	public function testPSRBridge() {
		$this->addPsrPackageFixtures();

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

		$this->removePsrPackageFixtures();

		// Must always throw exception if core PSR-15 implementation not used.
		if ( ! CODEGARAGE_PSR_PACKAGE_INSTALLED ) {
			$this->expectException( MiddlewarePsrNotFoundException::class );
			$this->expectExceptionMessage( 'Cannot find PSR15 HTTP Server Middleware.' );

			PipelineBridge::toMiddleware( middleware: '' );
		}
	}
}
