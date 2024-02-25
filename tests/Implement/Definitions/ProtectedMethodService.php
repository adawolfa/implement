<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

abstract class ProtectedMethodService
{

	abstract protected function foo(): void;

	public function bar(): void
	{
		$this->foo();
	}

}