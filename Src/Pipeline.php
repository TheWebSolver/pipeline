<?php
/**
 * Pipeline to follow the Chain of Responsibility Design Pattern.
 *
 * @package TheWebSolver\Codegarage\Library
 *
 * @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch, Squiz.Commenting.FunctionComment.IncorrectTypeHint -- Closure type-hint OK.
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib;

use Closure;
use Throwable;
use TheWebSolver\Codegarage\Lib\PipeInterface as Pipe;

/** Pipeline to follow the Chain of Responsibility Design Pattern. */
class Pipeline {
	/** The subject that gets transformed when passed through various pipes. */
	protected mixed $subject;

	/**
	 * Pipes being used.
	 *
	 * @var array<string|Closure|Pipe>
	 */
	protected array $pipes = array();

	/**
	 * Global arguments accepted by pipe's func/method parameter.
	 *
	 * @var mixed[]
	 */
	protected array $use;

	protected Closure $catcher;

	/**
	 * @throws InvalidPipe     When invalid pipe given.
	 * @throws InvalidPipeline When could not determine thrown exception.
	 * @phpstan-param class-string<Pipe>|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed $pipe
	 */
	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber -- Exactly 2 exception thrown.
	final public static function resolve( string|Closure|Pipe $pipe ): Closure {
		$isClassName = is_string( $pipe ) && class_exists( $pipe );

		try {
			return match ( true ) {
				default                  => throw InvalidPipe::from( $pipe ),
				$isClassName             => PipelineBridge::make( $pipe )->handle( ... ),
				$pipe instanceof Pipe    => $pipe->handle( ... ),
				$pipe instanceof Closure => $pipe,
			};
		} catch ( Throwable $e ) {
			if ( $e instanceof InvalidPipe ) {
				throw $e;
			}

			throw new InvalidPipeline( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Sets the global arguments accepted by pipe's func/method parameter.
	 *
	 * @example usage
	 * ```
	 * $param1  = is_string(...);
	 * $param2  = strtoupper(...);
	 * $subject = ' convert this to all caps  ';
	 * $result = (new Pipeline())
	 *  ->use($param1, $param2)
	 *  ->send($subject)
	 *  ->through(pipes: [
	 *   // Each pipe func/method accepts [#1] $subject and [#2] $next arguments by default.
	 *   // Then, $param1 = [#3] $isString; $param2 = [#4] $capitalize as global args.
	 *   // Not the best example but serves the purpose of passing additional args.
	 *   static function(string $subject, Closure $next, Closure $isString, Closure $capitalize) {
	 *    return $next(!$isString($subject) ? '' : $capitalize($subject));
	 *   }
	 *  ])->thenReturn();
	 * ```
	 */
	public function use( mixed ...$args ): static {
		$this->use = $args;

		return $this;
	}

	/** Registers the subject to be sent to each pipe in the pipeline. */
	public function send( mixed $subject ): static {
		$this->subject = $subject;

		return $this;
	}

	/**
	 * Registers pipes that will transform the registered subject.
	 *
	 * @param array<string|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed> $pipes
	 * @phpstan-param array<class-string<Pipe>|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed> $pipe
	 */
	public function through( array $pipes ): static {
		// Anything piped before this method must be deferred before passed pipes.
		// This happens when Pipeline::pipe() is invoked before this method.
		$deferrable  = $this->pipes ?? array();
		$this->pipes = $pipes;

		if ( ! empty( $deferrable ) ) {
			foreach ( $deferrable as $pipe ) {
				$this->pipe( $pipe );
			}
		}

		return $this;
	}

	/**
	 * Captures the pipe's exception that will blatantly abrupt the whole pipeline flow.
	 *
	 * When short-circuited pipeline will prevent remaining pipes operation, it fixes.
	 * Any exception thrown by the failing pipe can then be handled by the user.
	 * Its captured value will be returned instead of the `Pipeline::then()`.
	 *
	 * @param Closure(Throwable $e, mixed ...$use): mixed $fallback
	 * @example usage
	 * ```
	 * $result = (new Pipeline())
	 *  ->send(subject: [])
	 *  // Pipeline abrupt and empty string returned because first pipe throws an exception.
	 *  ->sealWith(fallback: fn(\TypeError $e) => '')
	 *  ->through(pipes: [
	 *   fn(mixed $subject, Closure $next) => is_string($subject)
	 *    ? $next(trim($subject))
	 *    : throw new \TypeError('Subject must be a string.'),
	 *   // Pipeline never passes subject to this pipe.
	 *   fn(mixed $subject, Closure $next) => $next(strtolower($subject))
	 *   ])->thenReturn();
	 * ```
	 */
	public function sealWith( Closure $fallback ): static {
		$this->catcher = $fallback;

		return $this;
	}

	/**
	 * Appends additional pipes to the pipeline stack.
	 *
	 * @param string|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed
	 * @phpstan-param class-string<Pipe>|Pipe|Closure(mixed $subject, Closure $next, mixed ...$use): mixed $pipe
	 */
	public function pipe( string|Closure|Pipe $pipe ): static {
		$this->pipes[] = $pipe;

		return $this;
	}

	/**
	 * Gets the transformed subject after passing through one last pipe.
	 *
	 * @param Closure(mixed $subject, mixed ...$use): mixed $return
	 * @throws InvalidPipe            When pipe type could not be resolved.
	 * @throws InvalidPipeline When a pipe abrupt the pipeline by throwing an exception & sealWith not used.
	 */
	// phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing -- Doesn't throw throwable.
	public function then( Closure $return ): mixed {
		$use     = $this->use ?? array();
		$pipes   = array_reverse( $this->pipes );
		$subject = $this->subject;

		try {
			return array_reduce( $pipes, $this->chain( ... ), $return )( $subject, ...$use );
		} catch ( Throwable $e ) {
			if ( $catcher = $this->catcher ?? null ) {
				return $catcher( $e, ...$use );
			}

			return $e instanceof InvalidPipe
				? throw $e
				: throw new InvalidPipeline( $e->getMessage(), $e->getCode(), $e );
		}
	}

	/**
	 * Passes through pipes in the pipeline and returns the transformed result.
	 *
	 * @throws InvalidPipe            When pipe type could not be resolved.
	 * @throws InvalidPipeline When a pipe abrupt the pipeline by throwing an exception & sealWith not used.
	 */
	public function thenReturn() {
		return $this->then( return: static fn( $transformed ) => $transformed );
	}

	/** Gets a Closure that wraps current pipe with the next pipe in the pipeline. */
	protected function chain( Closure $next, string|Closure|Pipe $current ): Closure {
		return fn ( $subject ) => self::resolve( $current )( $subject, $next, ...( $this->use ?? array() ) );
	}
}
