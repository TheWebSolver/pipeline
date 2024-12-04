<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use Exception;
use LogicException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use TheWebSolver\Codegarage\Lib\Pipeline;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Lib\PipelineBridge;
use TheWebSolver\Codegarage\Lib\Psr\Middleware;
use TheWebSolver\Codegarage\Test\Stub\ResponseStub;
use TheWebSolver\Codegarage\Test\Stub\MiddlewareStub;
use TheWebSolver\Codegarage\Lib\Interfaces\PipeInterface;
use TheWebSolver\Codegarage\Lib\Error\InvalidMiddlewareForPipe;

class BridgeTest extends TestCase {
	private mixed $expectedContainerInterfaceGetMethodReturnValue;

	protected function tearDown(): void {
		unset( $this->expectedContainerInterfaceGetMethodReturnValue );
	}

	private function mockFromContainerInterfaceGetMethod( string $id ): mixed {
		return self::class === $id
			? throw new class() extends Exception implements ContainerExceptionInterface {}
			: $this->expectedContainerInterfaceGetMethodReturnValue;
	}

	/** @dataProvider provideContainerEntryAndReturnValueForMiddleware */
	public function testMiddlewareConversionWithContainer(
		string|Closure|MiddlewareInterface $handler,
		mixed $returnedByContainer,
		int $noOfTimesInvoked,
		string $expectedMiddlewareClassName,
		bool $throws = false
	): void {
		if ( $throws ) {
			$this->expectException( InvalidMiddlewareForPipe::class );
		}

		$this->expectedContainerInterfaceGetMethodReturnValue = $returnedByContainer;

		/** @var ContainerInterface&MockObject */
		( $container = $this->createMock( ContainerInterface::class ) )
			->expects( $this->exactly( $noOfTimesInvoked ) )
			->method( 'get' )
			->with( $handler )
			->willReturnCallback( $this->mockFromContainerInterfaceGetMethod( ... ) );

		$this->assertInstanceOf(
			$expectedMiddlewareClassName,
			( new PipelineBridge( $container ) )->toMiddleware( $handler )
		);
	}

	public function provideContainerEntryAndReturnValueForMiddleware(): array {
		return array(
			array( MiddlewareStub::class, new MiddlewareStub(), 1, MiddlewareStub::class ),
			array( 'middlewareAsClosure', static function () {}, 1, Middleware::class ),
			array(
				static function () {},
				'"ContainerInterface::get()" is never invoked if handler is not a string value',
				0,
				Middleware::class,
			),
			array( new MiddlewareStub(), null, 0, MiddlewareStub::class ),
			array(
				self::class,
				$this,
				1,
				'All exceptions are converted to "InvalidMiddlewareForPipe"',
				true,
			),

		);
	}

	/** @dataProvider provideMiddlewares */
	public function testMiddlewareConversionWithoutContainer( mixed $middleware, ?string $thrown ): void {
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

	public function testExceptionThrownWithInvalidHandler(): void {
		$response = $this->createStub( ResponseInterface::class );
		$handler  = new class( $response ) /* does not implement RequestHandlerInterface */ {
			public function __construct( private ResponseInterface $response ) {}
		};

		$this->expectException( LogicException::class );

		( new PipelineBridge() )
			->middlewareToPipe( new MiddlewareStub() )
			->handle( $response, $this->fail( ... ), $this->createStub( ServerRequestInterface::class ), $handler::class );
	}
}
