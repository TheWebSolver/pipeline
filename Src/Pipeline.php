<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use Throwable;
use Psr\Container\ContainerInterface;
use TheWebSolver\Codegarage\Lib\PipeInterface as Handler;
use TheWebSolver\Codegarage\Lib\Interfaces\ChainOfResponsibility;

class Pipeline implements ChainOfResponsibility {
	private mixed $subject;
	private Closure $catcher;
	/** @var mixed[] */
	private array $args = array();
	/** @var array<class-string<Handler>|Handler|Closure(mixed $subject, Closure $next, mixed ...$args): mixed> */
	private array $pipes = array();

	public function __construct( private readonly ?ContainerInterface $container = null ) {}

	public static function normalizeException( Throwable $thrown, mixed $subject = null ): InvalidPipe|InvalidPipeline {
		return $thrown instanceof InvalidPipe ? $thrown : new InvalidPipeline( $thrown, $subject );
	}

	public function use( mixed ...$globalArgsForEachPipe ): static {
		$this->args = $globalArgsForEachPipe;

		return $this;
	}

	public function send( mixed $subject ): static {
		$this->subject = $subject;

		return $this;
	}

	public function through( array $pipes ): static {
		$deferredPipes = $this->pipes ?? array();
		$this->pipes   = $pipes;

		array_walk( $deferredPipes, $this->pipe( ... ) );

		return $this;
	}

	public function sealWith( Closure $fallback ): static {
		$this->catcher = $fallback;

		return $this;
	}

	public function pipe( string|Closure|Handler $handler ): static {
		$this->pipes[] = $handler;

		return $this;
	}

	public function then( Closure $handle ): mixed {
		$pipes = array_reverse( $this->pipes );

		try {
			return array_reduce( $pipes, $this->handle( ... ), $handle )( $this->subject, ...$this->args );
		} catch ( InvalidPipe | InvalidPipeline $error ) {
			return $error instanceof InvalidPipe ? throw $error : $this->throwOrSealPipeline( $error );
		}
	}

	public function thenReturn(): mixed {
		return $this->then( static fn( $transformed ) => $transformed );
	}

	private function handle( Closure $next, string|Closure|Handler $handler ): Closure {
		return function ( $subject ) use ( $handler, $next ) {
			try {
				return Pipe::create( $handler, $this->container )->handle( $subject, $next, ...$this->args );
			} catch ( Throwable $thrown ) {
				throw self::normalizeException( $thrown, $subject );
			}
		};
	}

	private function throwOrSealPipeline( InvalidPipeline $error ): mixed {
		return ( $catch = ( $this->catcher ?? null ) ) ? $catch( $error, ...$this->args ) : throw $error;
	}
}
