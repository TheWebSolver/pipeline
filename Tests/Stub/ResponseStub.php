<?php
declare( strict_types = 1 );

namespace TheWebSolver\Codegarage\Test\Stub;

use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;

class ResponseStub implements ResponseInterface {
	private int $statusCode;

	public function withProtocolVersion( string $version ): MessageInterface {
		return $this;
	}

	public function withAddedHeader( string $name, $value ): MessageInterface {
		return $this;
	}

	public function withBody( StreamInterface $body ): MessageInterface {
		return $this;
	}

	public function withHeader( string $name, $value ): MessageInterface {
		return $this;
	}

	public function withoutHeader( string $name ): MessageInterface {
		return $this;
	}

	public function hasHeader( string $name ): bool {
		return true;
	}

	public function withStatus( int $code, string $reasonPhrase = '' ): ResponseInterface {
		$new             = clone $this;
		$new->statusCode = $code;

		return $new;
	}

	public function getStatusCode(): int {
		return $this->statusCode;
	}

	public function getReasonPhrase(): string {
		return '';
	}

	public function getProtocolVersion(): string {
		return '1';
	}

	public function getBody(): StreamInterface {}

	public function getHeader( string $name ): array {
		return array();
	}

	public function getHeaderLine( string $name ): string {
		return '';
	}

	public function getHeaders(): array {
		return array();
	}
}
