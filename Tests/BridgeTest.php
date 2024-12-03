<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use TheWebSolver\Codegarage\Lib\Pipeline;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheWebSolver\Codegarage\Lib\PipeInterface;
use TheWebSolver\Codegarage\Lib\PipelineBridge;
use TheWebSolver\Codegarage\Test\Stub\ResponseStub;
use TheWebSolver\Codegarage\Test\Stub\MiddlewareStub;
use TheWebSolver\Codegarage\Lib\InvalidMiddlewareForPipe;

class BridgeTest extends TestCase {
	/** @dataProvider provideMiddlewares */
	public function testMiddlewareConversion( mixed $middleware, ?string $thrown ): void {
		if ( $thrown ) {
			$this->expectException( $thrown );
		}

		$instance = ( new PipelineBridge() )->toMiddleware( $middleware );

		$this->assertInstanceOf( MiddlewareInterface::class, $instance );
	}

	/** @dataProvider provideMiddlewares */
	public function testMiddlewareToPipeConversion( mixed $middleware, ?string $thrown ): void {
		$this->assertInstanceOf( PipeInterface::class, ( new PipelineBridge() )->middlewareToPipe( $middleware ) );
	}

	/** @return array<mixed[]>*/
	public function provideMiddlewares(): array {
		$responseStub = $this->createStub( ResponseInterface::class );

		return array(
			array( MiddlewareStub::class, null ),
			array( $this->createMock( MiddlewareInterface::class ), null ),
			array( '\\Invalid\\Middleware', InvalidMiddlewareForPipe::class ),
			array( static::class, InvalidMiddlewareForPipe::class ),
			array( fn( ServerRequestInterface $r, RequestHandlerInterface $h ) => $responseStub, null ),
			array(
				new class( $responseStub ) implements MiddlewareInterface {
					public function __construct( private readonly ResponseInterface $response ) {}

					public function process( ServerRequestInterface $request, RequestHandlerInterface $handler ): ResponseInterface {
						return $this->response;
					}
				},
				null,
			),
		);
	}

	public function testPipelineBridgeWithPsr() {
		/** @var ServerRequestInterface */
		$request = $this->createStub( ServerRequestInterface::class );

		$handler = new class() implements RequestHandlerInterface {
			public function handle( ServerRequestInterface $request ): ResponseInterface {
				$middlewares[] = MiddlewareStub::class;
				$middlewares[] = static function ( ServerRequestInterface $request, RequestHandlerInterface $h ) {
					return ( $r = $h->handle( $request ) )->withStatus( $r->getStatusCode() + 50 );
				};

				$middlewares[] = new class() implements MiddlewareInterface {
					public function process( ServerRequestInterface $request, RequestHandlerInterface $h ): ResponseInterface {
						return ( $r = $h->handle( $request ) )->withStatus( $r->getStatusCode() + 250 );
					}
				};

				return ( new Pipeline() )
					->use( $request )
					->send( subject: ( new ResponseStub() )->withStatus( code: 100 ) )
					->through( array_map( ( new PipelineBridge() )->middlewareToPipe( ... ), $middlewares ) )
					->thenReturn();
			}
		};

		$this->assertSame( expected: 500, actual: $handler->handle( $request )->getStatusCode() );
	}
}
