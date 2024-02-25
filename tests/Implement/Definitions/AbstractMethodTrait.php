<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

trait AbstractMethodTrait
{

	abstract function traitMethod(#[Attribute('param')] ?string $foo = null);

}