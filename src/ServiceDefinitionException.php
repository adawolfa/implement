<?php declare(strict_types=1);

namespace Adawolfa\Implement;

use ReflectionClass;
use ReflectionMethod;

final class ServiceDefinitionException extends LogicException
{

	public static function cannotImplementMethod(string $what, ReflectionMethod $method): self
	{
		return new self("Cannot implement $what {$method->getDeclaringClass()->name}::$method->name().");
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 */
	public static function cannotCreateImplementationFor(string $what, ReflectionClass $reflection): self
	{
		return new ServiceDefinitionException("Cannot create implementation for $what $reflection->name.");
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 */
	public static function cannotImplementConstructor(
		string           $what,
		ReflectionClass  $reflection,
		ReflectionMethod $constructorReflection,
	): self
	{
		return new self(
			sprintf(
				'Cannot implement %s with %s constructor %s::__construct().',
				$reflection->name,
				$what,
				$constructorReflection->getDeclaringClass()->name,
			),
		);
	}

}