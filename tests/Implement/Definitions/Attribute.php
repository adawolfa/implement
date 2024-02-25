<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

use Attribute as PHPAttribute;

#[PHPAttribute(PHPAttribute::TARGET_ALL | PHPAttribute::IS_REPEATABLE)]
final class Attribute
{

	public function __construct(public string $foo, public int $bar = 0)
	{
	}

}
