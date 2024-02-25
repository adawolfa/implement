<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

abstract class ConstructorService extends ConstructorServiceBar implements ServiceInterface
{

	protected function __construct(string $foo, string $bar)
	{
		parent::__construct($bar, $foo);
	}

}