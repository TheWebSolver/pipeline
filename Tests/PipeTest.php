<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\Pipe;
use PHPUnit\Framework\MockObject\MockObject;
use TheWebSolver\Codegarage\Test\Stub\PipeStub;
use TheWebSolver\Codegarage\Lib\Error\InvalidPipe;
use TheWebSolver\Codegarage\Lib\Interfaces\PipeInterface;

class PipeTest extends TestCase {
	/** @dataProvider provideContainerEntryAndReturnValueForPipe */
	public function testPipeConversionWithContainer(
		string $entry,
		mixed $resolved,
		bool $throws = false
	): void {
		if ( $throws ) {
			$this->expectException( InvalidPipe::class );
		}

		/** @var ContainerInterface&MockObject */
		$container = $this->createMock( ContainerInterface::class );

		$container->expects( $this->once() )
			->method( 'get' )
			->with( $entry )
			->willReturn( $resolved );

		$pipe = Pipe::create( $entry, $container );

		$this->assertInstanceOf( Pipe::class, $pipe );
	}

	public function provideContainerEntryAndReturnValueForPipe(): array {
		return array(
			array( PipeStub::class, new PipeStub() ),
			array( 'pipeAsClosure', static function () {}, false ),
			array( 'neitherPipeNorClosure', 'will throw exception', true ),
		);
	}

	/** @dataProvider provideVariousPipes */
	public function testPipeConversionWithoutContainer( mixed $handler ): void {
		$this->assertInstanceOf( Pipe::class, Pipe::create( $handler, container: null ) );
	}

	/** @return array<mixed[]> */
	public function provideVariousPipes(): array {
		return array(
			array( fn( $subject, $next ) => $next( $subject ) ),
			array( PipeStub::class ),
			array( new Pipe( function () {} ) ),
			array(
				new class() implements PipeInterface {
					public function handle( mixed $subject, Closure $next, mixed ...$args ): mixed {
						return $next( $subject );
					}
				},
			),
		);
	}
}
