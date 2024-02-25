<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

use Override;

abstract class MethodImplementedService implements ServiceInterface
{

	#[Override]
	public function method(): void
	{
	}

}