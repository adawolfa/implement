<?php declare(strict_types=1);

namespace Adawolfa\Implement\Call;

use Adawolfa\Implement\LogicException;
use Adawolfa\Implement\RuntimeException;
use ArrayAccess;
use Countable;
use Override;
use ReflectionMethod;
use ReflectionParameter;

/**
 * @implements ArrayAccess<int|string, mixed>
 */
final class Arguments implements ArrayAccess, Countable
{

	/**
	 * @param array<int, mixed> $arguments
	 */
	public function __construct(private array &$arguments, private readonly ReflectionMethod $method)
	{
	}

	#[Override]
	public function offsetExists(mixed $offset): bool
	{
		try {
			$parameter = $this->getParameterReflection($offset);
		} catch (UnknownParameterException) {
			return false;
		}

		return isset($this->arguments[$parameter->getPosition()]);
	}

	#[Override]
	public function offsetGet(mixed $offset): mixed
	{
		$parameter = $this->getParameterReflection($offset);

		if (array_key_exists($parameter->getPosition(), $this->arguments)) {
			return $this->arguments[$parameter->getPosition()];
		}

		if (!$parameter->isDefaultValueAvailable()) {
			throw new RuntimeException(
				'No default value available for $' . $parameter->name
				. " of {$this->method->getDeclaringClass()->name}::{$this->method->name}().",
			);
		}

		return $parameter->getDefaultValue();
	}

	#[Override]
	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!is_string($offset) && !is_int($offset)) {
			throw new LogicException("Argument can be accessed by parameter name (string) or position (integer).");
		}

		$parameter = $this->getParameterReflection($offset);

		if (!$parameter->isPassedByReference()) {
			throw new CannotModifyArgumentException(
				'Cannot modify argument $' . $parameter->name
				. " of {$this->method->getDeclaringClass()->name}::{$this->method->name}()"
				. " because it is not passed by reference.",
			);
		}

		$this->arguments[$parameter->getPosition()] = $value;
	}

	#[Override]
	public function offsetUnset(mixed $offset): void
	{
		throw new LogicException('Cannot unset an argument.');
	}

	#[Override]
	public function count(): int
	{
		return count($this->arguments);
	}

	/**
	 * @return array<int, mixed>
	 */
	public function toArray(): array
	{
		return $this->arguments;
	}

	private function getParameterReflection(string|int $name): ReflectionParameter
	{
		foreach ($this->method->getParameters() as $parameter) {

			if ($parameter->getPosition() === $name || $parameter->getName() === $name) {
				return $parameter;
			}

		}

		$description = is_string($name) ? '$' . $name : '#' . $name;

		throw new UnknownParameterException(
			"Unknown parameter $description for {$this->method->getDeclaringClass()->name}::{$this->method->name}().",
		);
	}

}