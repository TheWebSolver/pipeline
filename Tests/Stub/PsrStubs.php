<?php
/**
 * Stub adapters for PSR7 and PSR15.
 *
 * @package TheWebSolver\Codegarage\Test
 *
 * @phpcs:disable Universal.Namespaces.DisallowCurlyBraceSyntax.Forbidden
 * @phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 * @phpcs:disable Universal.Namespaces.OneDeclarationPerFile.MultipleFound
 * @phpcs:disable Universal.Namespaces.DisallowDeclarationWithoutName.Forbidden
 */

declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Lib\Psr7Adapter {
	interface ServerRequestInterface {
		public function withAttribute( string $name, mixed $value ): static;
		public function getAttribute( string $name, mixed $default = null ): mixed;
	}

	interface ResponseInterface {
		public function withStatus( int $code ): static;
		public function getStatusCode(): int;
	}

	class Request implements ServerRequestInterface {
		/** @var array<string,mixed> */
		private array $attribute;

		public function withAttribute( string $name, mixed $value ): static {
			$new                     = clone $this;
			$new->attribute[ $name ] = $value;

			return $new;
		}

		public function getAttribute( string $name, mixed $default = null ): mixed {
			return $this->attribute[ $name ] ?? $default;
		}
	}

	class Response implements ResponseInterface {
		private int $statusCode;

		public function withStatus( int $code ): static {
			$new             = clone $this;
			$new->statusCode = $code;

			return $new;
		}

		public function getStatusCode(): int {
			return $this->statusCode;
		}
	}
}

namespace TheWebSolver\Codegarage\Lib\Psr15Adapter {
	use TheWebSolver\Codegarage\Lib\PipelineBridge;
	use TheWebSolver\Codegarage\Lib\Psr7Adapter\{ ServerRequestInterface, ResponseInterface };

	interface RequestHandlerInterface {
		public function handle( ServerRequestInterface $request ): ResponseInterface;
	}

	interface MiddlewareInterface {
		public function process(
			ServerRequestInterface $request,
			RequestHandlerInterface $handler
		): ResponseInterface;
	}

	class Middleware implements MiddlewareInterface {
		public function process(
			ServerRequestInterface $request,
			RequestHandlerInterface $handler
		): ResponseInterface {
			$response = $request
				->getAttribute( name: PipelineBridge::MIDDLEWARE_RESPONSE )
				->withStatus( code: 200 );

			return $response;
		}
	}
}

namespace {
	use TheWebSolver\Codegarage\Lib\Psr7Adapter\{ ServerRequestInterface, ResponseInterface };
	use TheWebSolver\Codegarage\Lib\Psr15Adapter\{ MiddlewareInterface, RequestHandlerInterface };

	class MiddlewareAdapter implements MiddlewareInterface {
		public function __construct( private readonly \Closure $middleware ) {}

		public function process(
			ServerRequestInterface $request,
			RequestHandlerInterface $handler
		): ResponseInterface {
			return ( $this->middleware )( $request, $handler );
		}
	}
}
