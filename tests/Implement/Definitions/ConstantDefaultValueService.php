<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

interface ConstantDefaultValueService
{

	public const int VALUE = 1;

	function foo(int $value = self::VALUE);

}