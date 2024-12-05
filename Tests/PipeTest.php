<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test;

use Closure;
use Exception;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Pipeline\Pipe;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Container\ContainerExceptionInterface;
use TheWebSolver\Codegarage\Test\Stub\PipeStub;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidPipe;
use TheWebSolver\Codegarage\Pipeline\Error\InvalidPipeline;
use TheWebSolver\Codegarage\Pipeline\Interfaces\PipeInterface;

class PipeTest extends TestCase {
	private mixed $expectedContainerInterfaceGetMethodReturnValue;

	protected function tearDown(): void {
		unset( $this->expectedContainerInterfaceGetMethodReturnValue );
	}

	private function mockFromContainerInterfaceGetMethod( string $id ): mixed {
		return self::class === $id
			? throw new class() extends Exception implements ContainerExceptionInterface {}
			: $this->expectedContainerInterfaceGetMethodReturnValue;
	}

	/**
	 * @dataProvider provideContainerEntryAndReturnValueForPipe
	 * @throws Exception For testing.
	 */
	public function testPipeConversionWithContainer(
		string|Closure|PipeInterface $handler,
		mixed $returnedByContainer,
		int $noOfTimesInvoked,
		string $expectedPipeClassName,
		?string $thrown = null
	): void {
		if ( $thrown ) {
			$this->expectException( $thrown );
		}

		$this->expectedContainerInterfaceGetMethodReturnValue = $returnedByContainer;

		/** @var ContainerInterface&MockObject */
		( $container = $this->createMock( ContainerInterface::class ) )
			->expects( $this->exactly( $noOfTimesInvoked ) )
			->method( 'get' )
			->with( $handler )
			->willReturnCallback( $this->mockFromContainerInterfaceGetMethod( ... ) );

		$this->assertInstanceOf( $expectedPipeClassName, Pipe::create( $handler, $container ) );
	}

	public function provideContainerEntryAndReturnValueForPipe(): array {
		return array(
			array( PipeStub::class, new PipeStub(), 1, PipeStub::class ),
			array( 'pipeAsClosure', static function () {}, 1, Pipe::class ),
			array(
				static function () {},
				'"ContainerInterface::get()" is never invoked if handler is not a string value',
				0,
				Pipe::class,
			),
			array( new PipeStub(), null, 0, PipeStub::class ),
			array(
				'Neither "PipeInterface" Nor Closure returned by "ContainerInterface::get()"',
				'will throw an "InvalidPipe" exception',
				1,
				'',
				InvalidPipe::class,
			),
			array(
				self::class,
				$this,
				1,
				'Exceptions except "InvalidPipe" is converted to "InvalidPipeline"',
				InvalidPipeline::class,
			),

		);
	}

	/** @dataProvider provideVariousPipes */
	public function testPipeConversionWithoutContainer( mixed $handler, ?string $expectedPipe = null ): void {
		$this->assertInstanceOf( $expectedPipe ?? Pipe::class, Pipe::create( $handler, container: null ) );
	}

	/** @return array<mixed[]> */
	public function provideVariousPipes(): array {
		return array(
			array( fn( $subject, $next ) => $next( $subject ) ),
			array( PipeStub::class, PipeStub::class ),
			array( new Pipe( function () {} ) ),
			array(
				$anonymousPipe = new class() implements PipeInterface {
					public function handle( mixed $subject, Closure $next, mixed ...$args ): mixed {
						return $next( $subject );
					}
				},
				$anonymousPipe::class,
			),
		);
	}
}
