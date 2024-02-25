<?php declare(strict_types=1);

namespace Adawolfa\Implement\Internal;

use Adawolfa\Implement\Call;
use Adawolfa\Implement\Handler;
use Adawolfa\Implement\ReflectionException;
use ReflectionException as PHPReflectionException;
use ReflectionObject;

/**
 * Trait injected to generated implementations.
 *
 * @internal
 */
trait CallHandle
{

	private readonly Handler $__handler;

	private ?ReflectionObject $__reflection = null;

	private ?bool $__handlerReturnsByReference = null;

	private function __handle(string $method, array &$args): mixed
	{
		return $this->__handler->handle($this->__prepareCall($method, $args));
	}

	private function &__handleByRef(string $method, array &$args): mixed
	{
		return $this->__handler->handle($this->__prepareCall($method, $args));
	}

	private function __prepareCall(string $method, array &$args): Call
	{
		$this->__reflection ??= new ReflectionObject($this);

		try {
			$methodReflection = $this->__reflection->getMethod($method);
		} catch (PHPReflectionException $e) {
			throw new ReflectionException('Could not reflect ' . $this::class . "::$method().", previous: $e);
		}

		$arguments = new Call\Arguments($args, $methodReflection);
		return new Call($this, $methodReflection, $this->__reflection, $arguments);
	}

	private function __doesHandlerReturnByReference(): bool
	{
		if ($this->__handlerReturnsByReference === null) {

			$handlerReflection = new ReflectionObject($this->__handler);

			try {
				$handleReflection  = $handlerReflection->getMethod('handle');
			} catch (PHPReflectionException $e) {
				throw new ReflectionException("Could not reflect $handlerReflection->name::$method().", previous: $e);
			}

			$this->__handlerReturnsByReference = $handleReflection->returnsReference();

		}

		return $this->__handlerReturnsByReference;
	}

}
