<?php
// @phpcs:disable Squiz.Commenting.FunctionComment.IncorrectTypeHint
// @phpcs:disable Squiz.Commenting.FunctionComment.ParamNameNoMatch
// @phpcs:disable Squiz.Commenting.FunctionCommentThrowTag.Missing

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Pipeline;

use Closure;
use Psr\Container\ContainerInterface;

/** @template TType */
trait Resolver {
	/**
	 * @param string|Closure|TType                        $handler
	 * @param array{0:class-string<TType>,1:class-string} $types
	 * @return TType
	 */
	private static function resolve( string|object $handler, ?ContainerInterface $container, array $types ): object {
		[ $type, $error ] = $types;
		$resolved         = ! is_string( $handler ) ? $handler : ( $container?->get( $handler ) ?? new $handler() );

		return match ( true ) {
			$resolved instanceof $type   => $resolved,
			$resolved instanceof Closure => new self( $resolved ),
			default                      => throw $error::from( $handler ),
		};
	}
}
