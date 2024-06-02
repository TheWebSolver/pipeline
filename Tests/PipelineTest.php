<?php
/**
 * Pipeline test.
 *
 * @package TheWebSolver\Codegarage\Test
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage;

use Closure;
use Exception;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use TheWebSolver\Codegarage\Lib\Pipeline;
use TheWebSolver\Codegarage\Stub\PipeStub;
use TheWebSolver\Codegarage\Lib\InvalidPipe;
use TheWebSolver\Codegarage\Lib\PipeInterface;
use TheWebSolver\Codegarage\Lib\InvalidPipeline;

class PipelineTest extends TestCase {
	/** @dataProvider provideVariousPipeTypes */
	public function testPipeResolver( mixed $pipe, ?string $thrown ): void {
		if ( $thrown ) {
			$this->expectException( $thrown );
		}

		$this->assertSame(
			expected: 'test',
			actual: Pipeline::resolve( $pipe )(
				subject: 'test',
				next: static fn( mixed $subject ): string => $subject
			)
		);
	}

	/** @return mixed[] */
	public static function provideVariousPipeTypes(): array {
		return array(
			array( PipeStub::class, null ),
			array( static fn ( string $subject, Closure $next ): string => $next( $subject ), null ),
			array( '\\Undefined\\ClassName', InvalidPipe::class ),
			array( static::class, InvalidPipeline::class ),
			array(
				new class() implements PipeInterface {
					public function handle( mixed $subject, Closure $next, mixed ...$use ): mixed {
						return $subject;
					}
				},
				null,
			),
		);
	}

	/** @dataProvider provideVariousPipes */
	public function testVariousPipeType( mixed $expected, mixed $subject, mixed $pipe ): void {
			$this->assertSame(
				expected: $expected,
				actual: ( new Pipeline() )->send( $subject )->pipe( $pipe )->thenReturn()
			);
	}

	/** @return mixed[] */
	public static function provideVariousPipes(): array {
		return array(
			array(
				'USING CLOSURE',
				'using closure',
				static fn ( string $subject, Closure $next ): string => $next( strtoupper( $subject ) ),
			),
			array(
				'USING PIPE CONCRETE',
				'using pipe concrete',
				new class() implements PipeInterface {
					public function handle( mixed $subject, Closure $next, mixed ...$use ): mixed {
						return strtoupper( $subject );
					}
				},
			),
			array(
				array( 1, 2, 3, 4, 5 ),
				array( 1, 2, 3 ),
				static fn ( array $subject, Closure $next ): array => $next( array( ...$subject, 4, 5 ) ),
			),
			array( 'using Pipe classname', 'using Pipe classname', PipeStub::class ),
		);
	}

	public function testMultiplePipes(): void {
		$this->assertSame(
			message: 'The return value must be of the last pipe passed to "Pipeline::through" method.',
			expected: 'UPPERCASE AND TRIM',
			actual: ( new Pipeline() )
				->send( subject: ' upperCase and Trim ' )
				->through(
					pipes: array(
						static fn ( string $subject, Closure $next ): string => $next( strtoupper( $subject ) ),
						static fn ( string $subject, Closure $next ): string => $next( trim( $subject ) ),
					)
				)
				->thenReturn()
		);
	}

	public function testPassingPipesUsingDifferentMethod(): void {
		$pipeline = ( new Pipeline() )
			->send( subject: 'test' )
			->pipe(
				static fn ( array $subject, Closure $next ): array => $next( array( ...$subject, 'defer' ) )
			)->pipe(
				static fn ( array $subject, Closure $next ): array => $next( array( ...$subject, 'pipes' ) )
			);

		$pipeline->through(
			array( static fn ( string $subject, Closure $next ): array => $next( array( $subject ) ) )
		);

		$this->assertSame(
			message: 'Pipes passed using "::pipe()" must be deferred if called before "::through()"',
			expected: array( 'test', 'defer', 'pipes' ),
			actual: $pipeline->thenReturn()
		);
	}

	public function testAdditionalArgsToPipe(): void {
		$this->assertSame(
			expected: 'UPPERCASE WITH ADDITIONAL ARGS',
			actual: ( new Pipeline() )
				->use( strtoupper( ... ), trim( ... ) )
				->send( subject: "\tuppercase With additional args\n" )
				->through(
					pipes: array(
						static fn ( string $subject, Closure $next, Closure $capitalize ): string
							=> $next( $capitalize( $subject ) ),
						static fn ( string $subject, Closure $next, Closure $capitalize, Closure $trim ): string
							=> $next( $trim( $subject ) ),
					)
				)->thenReturn()
		);
	}

	public function testFinalTransformationPipe(): void {
		$this->assertSame(
			expected: array( 1, 2, 3, 4, 5 ),
			actual: ( new Pipeline() )
				->send( subject: array( 1, 2, 3 ) )
				->pipe(
					static fn ( array $subject, Closure $next ): array => $next( array( ...$subject, 4 ) )
				)->then( return: static fn ( array $subject ): array => array( ...$subject, 5 ) )
		);
	}

	public function testPipeSealer(): void {
		$this->assertSame(
			message: 'The return value must be whatever "Pipeline::sealWith()" method returns.',
			expected: 'Exception message',
			actual: ( new Pipeline() )
				->send( subject: 'Never Used' )
				->sealWith( fallback: static fn ( Exception $e ): string => $e->getMessage() )
				->through(
					pipes: array(
						static fn ( string $subject, Closure $next ): string => $next( $subject ),
						static fn () => throw new Exception( message: 'Exception message' ),
					)
				)
				->thenReturn()
		);

		// When "Pipeline::sealWith()" is not used, exception is thrown.
		$this->expectException( InvalidPipeline::class );

		( new Pipeline() )
			->send( subject: 'test' )
			->pipe( static fn () => throw new RuntimeException() )
			->thenReturn();
	}

	public function testInitialTransformation(): void {
		$this->assertSame(
			expected: 12346,
			actual: ( new Pipeline() )
				->send( subject: '12345' )
				->pipe( static fn( string $subject, Closure $next ): int => $next( (int) ( $subject + 1 ) ) )
				->thenReturn()
		);
	}

	public function testExampleCodes(): void {
		$pipeline = new Pipeline();

		$pipeline->use(is_string(...), strtoupper(...))
			->send(subject: ' convert this to all caps  ')
			->sealWith(fallback: static fn(\Throwable $e): string => $e->getMessage())
			->through(pipes: [
				// $isString is the first value passed to "Pipeline::use()".
				static function(mixed $subject, Closure $next, Closure $isString): string {
					return $next(!$isString($subject) ? '' : $subject);
				},

				// $uppercase is the second value passed to "Pipeline::use()".
				static function(mixed $subject, Closure $next, Closure $isString, Closure $uppercase): string {
					return $next($uppercase($subject));
				},

				// We'll convert our subject into an array.
				static fn(mixed $subject, Closure $next): array => $next(array($subject)),

				// Final check if our subject remains same type.
				static function(mixed $subject, Closure $next, Closure $isString): array {
					return $isString($subject)
						? $next($subject)
						: throw new \TypeError('Subject transformed into an array');
				}
			])
			// Subject never reaches to this pipe.
			->pipe( static fn(mixed $subject, Closure $next): array
				=> $next(is_array($subject) ? array(...$subject, 'suffix') : array()));

		// Last pipe throws exception, so we'll get exception message instead of transformed subject.
		$transformed = $pipeline->then(
			static fn(mixed $subject) => array('prefix', ...(is_array($subject) ? $subject : array()))
		);

		$this->assertSame(expected: 'Subject transformed into an array', actual: $transformed);
	}
}
