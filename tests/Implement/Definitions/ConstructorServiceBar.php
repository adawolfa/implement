<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

abstract class ConstructorServiceBar extends ConstructorServiceFoo
{

	public function __construct(public string $bar, string $foo)
	{
		parent::__construct($foo);
	}

}