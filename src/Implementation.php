<?php declare(strict_types=1);

namespace Adawolfa\Implement;

use ReflectionClass;

/**
 * Generated service implementation factory.
 *
 * @template T of object
 */
final readonly class Implementation
{

	/**
	 * @param ReflectionClass<T> $reflection
	 */
	public function __construct(private ReflectionClass $reflection)
	{
	}

	/**
	 * Instantiates the generated implementation.
	 * @param Handler $handler call handler
	 * @param mixed   $args    parameters for constructor, if any
	 * @return T
	 * @noinspection PhpDocSignatureInspection
	 */
	public function construct(Handler $handler, mixed ...$args): object
	{
		return new $this->reflection->name($handler, ...$args);
	}

}