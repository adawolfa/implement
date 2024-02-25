<?php declare(strict_types=1);

namespace Adawolfa\Implement;

use Adawolfa\Implement\Call\Arguments;
use ReflectionMethod;
use ReflectionObject;

/**
 * Service method call.
 */
final readonly class Call
{

	public function __construct(
		public object           $service,
		public ReflectionMethod $method,
		public ReflectionObject $reflection,
		public Arguments        $arguments,
	)
	{
	}

}