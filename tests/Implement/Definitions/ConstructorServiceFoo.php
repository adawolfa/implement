<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

abstract class ConstructorServiceFoo
{

	public function __construct(public string $foo)
	{
	}

}