## Introduction

Pipeline follows the Chain of Responsibility Design Pattern to handle the given subject/request using pipes.

## Installation (via Composer)

Install library using composer command:
```sh
$ composer require thewebsolver/pipeline
```

## Usage

The main responsibility of the Pipeline class is to pass given subject to all registered pipes and return back the transformed value. The pipeline chain can be initialized following builder pattern as follows:
- Initialize the pipeline (`new Pipeline()`).
- Pass subject to the pipeline using `Pipeline::send()` method.
- Provide a single handler using `Pipeline::pipe()` method or if multiple pipes (_which obviously should be the case, otherwise what is the intent of using Pipeline anyway_), use `Pipeline::through()` method.
	> NOTE: `Pipeline::through()` is the main method to provide pipes. If pipes are provided using `Pipeline::pipe()` method and then required pipes are provided using `Pipeline::through()` method, pipes provided using `Pipeline::pipe()` will be deferred. Meaning subject will pass through pipes provided using `Pipeline::through()` and then the transformed subject is pass through other pipes provided using `Pipeline::pipe()`.

	> In summary, `Pipeline::pipe()` method's intent is to append additional pipes to the pipeline after required pipes are provided using `Pipeline::through()` method.

	> Subject is passed through pipes in the same order as they are provided.

- Finally, get the transformed subject back using `Pipeline::thenReturn()` method. If subject needs to be transformed one last time before receiving it, `Pipeline::then()` can be used.

There are two methods that may be required depending on how subject is being handled and how pipes behave.
- `Pipeline::use()` method provides additional data to each pipe.
- `Pipeline::sealWith()` method provides prevention mechanism form script interruption if any pipe throws an exception.

### Basic Usage
```php
use TheWebSolver\Codegarage\Lib\Pipeline;

// Pipe as a PipeInterface.
class MakeTextUpperCase implements PipeInterface {
	public function handle(string $text, \Closure $next): string {
		return strtoupper($text);
	}
}

// Pipe as a lambda function.
$trimCharacters = static fn(string $text, \Closure $next): string => $next( trim( $text ) );

// $finalText will be -> 'WHITESPACE AND MIXED CASED STRING';
$finalText = (new Pipeline())
	->send(subject: ' Whitespace and Mixed cased String\ ')
	->through(pipes: array( MakeTextUpperCase::class, $trimCharacters ) )
	->thenReturn();
```


### Advanced Usage
```php
use Closure;
use Throwable;
use TheWebSolver\Codegarage\Lib\Pipeline;

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
```

## PSR-7 & PSR-15 Bridge
The `TheWebSolver\Codegarage\Lib\PipelineBridge` seamlessly handles middlewares coming to the Request Handler, piping through the pipeline and getting the response back.

```php
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ServerRequestInterface;
use TheWebSolver\Codegarage\Lib\PipelineBridge;

class RequestHandler implements RequestHandlerInterface {
	/** @var array<Closure|MiddlewareInterface> */
	protected array $middlewares;

	public function addMiddleware(Closure|MiddlewareInterface $middleware) {
		$this->middlewares[] = PipelineBridge::middlewareToPipe($middleware);
	}

	public function handle( ServerRequestInterface $request ): ResponseInterface {
		// Concrete that implements "ResponseInterface".
		$response = new Response();

		// Middleware that uses previous response as required.
		$middleware = function(
			ServerRequestInterface $request,
			RequestHandlerInterface $handler
		) {
			$previousResponse = $request->getAttribute(PipelineBridge::MIDDLEWARE_RESPONSE);

			return '/app/route/' !== $request->getRequestTarget()
				? new Response(status: 404)
				: $previousResponse->withStatus(code: 200);
		};

		$middlewares = array(
			...$this->middlewares,
			PipelineBridge::middlewareToPipe($middleware)
		);

		return (new Pipeline())
			->use($request, $this) // Passed to "MiddlewareInterface::process()" method.
			->send(subject: $response)
			->through(pipes: $middlewares)
			->thenReturn();
	}
}
```
