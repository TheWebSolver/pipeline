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
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TheWebSolver\Codegarage\Pipeline\Bridge;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Pipeline\Pipeline;
use TheWebSolver\Codegarage\Pipeline\Middleware;
use TheWebSolver\Codegarage\Test\Stub\ResponseStub;
use TheWebSolver\Codegarage\Test\Stub\MiddlewareStub;
use TheWebSolver\Codegarage\Test\Stub\RequestHandlerStub;
use TheWebSolver\Codegarage\Pipeline\Interfaces\PipeInterface;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidMiddlewareForPipe;

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

		$this->assertInstanceOf( $expectedMiddlewareClassName, Middleware::create( $handler, $container ) );
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
	public function testMiddlewareConversionWithoutContainer( mixed $middleware, ?string $thrown = null ): void {
		if ( $thrown ) {
			$this->expectException( $thrown );
		}

		$this->assertInstanceOf( MiddlewareInterface::class, Middleware::create( $middleware ) );
	}

	/** @dataProvider provideMiddlewares */
	public function testMiddlewareToPipeConversion( mixed $middleware, ?string $thrown = null ): void {
		$this->assertInstanceOf( PipeInterface::class, Middleware::toPipe( $middleware ) );
	}

	/** @return array<mixed[]>*/
	public function provideMiddlewares(): array {
		$responseStub = $this->createStub( ResponseInterface::class );

		return array(
			array( MiddlewareStub::class ),
			array( $this->createMock( MiddlewareInterface::class ) ),
			array( '\\Invalid\\Middleware', InvalidMiddlewareForPipe::class ),
			array( static::class, InvalidMiddlewareForPipe::class ),
			array( fn( ServerRequestInterface $r, RequestHandlerInterface $h ) => $responseStub ),
			array(
				new class( $responseStub ) implements MiddlewareInterface {
					public function __construct( private readonly ResponseInterface $response ) {}

					public function process( ServerRequestInterface $request, RequestHandlerInterface $handler ): ResponseInterface {
						return $this->response;
					}
				},
			),
		);
	}

	private function getMiddlewares(): array {
		$middlewares   = array( MiddlewareStub::class );
		$middlewares[] = static function ( ServerRequestInterface $request, RequestHandlerInterface $h ) {
			return ( $r = $h->handle( $request ) )->withStatus( $r->getStatusCode() + 50 );
		};

		$middlewares[] = new class() implements MiddlewareInterface {
			public function process( ServerRequestInterface $request, RequestHandlerInterface $h ): ResponseInterface {
				return ( $r = $h->handle( $request ) )->withStatus( $r->getStatusCode() + 250 );
			}
		};

		return $middlewares;
	}

	public function testPipelineBridgeWithPsr(): void {
		/** @var ServerRequestInterface */
		$request  = $this->createStub( ServerRequestInterface::class );
		$response = ( new ResponseStub() )->withStatus( 100 );
		$pipes    = array_map( Middleware::toPipe( ... ), $this->getMiddlewares() );
		$handler  = new RequestHandlerStub( ( new Pipeline() )->use( $request )->send( $response )->through( $pipes ) );

		$this->assertSame( expected: 500, actual: $handler->handle( $request )->getStatusCode() );

		$handler = new RequestHandlerStub( ( new Bridge() )->for( $request, $response )->through( ...$pipes ) );

		$this->assertSame( expected: 500, actual: $handler->handle( $request )->getStatusCode() );

		$handler = new RequestHandlerStub(
			( new Bridge() )->for( $request, $response )->throughMiddlewares( ...$this->getMiddlewares() )
		);

		$this->assertSame( expected: 500, actual: $handler->handle( $request )->getStatusCode() );
	}

	public function testExceptionThrownWithInvalidHandler(): void {
		$response = $this->createStub( ResponseInterface::class );
		$handler  = new class( $response ) /* does not implement RequestHandlerInterface */ {
			public function __construct( private ResponseInterface $response ) {}
		};

		$this->expectException( LogicException::class );

		Middleware::toPipe( new MiddlewareStub() )->handle(
			$response,
			$this->fail( ... ),
			$this->createStub( ServerRequestInterface::class ),
			$handler::class
		);
	}
}
