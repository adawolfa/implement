<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Internal;

use Adawolfa\Implement\Call;
use Adawolfa\Implement\Handler;
use Adawolfa\Implement\Internal\CallHandle;
use Adawolfa\Implement\ReflectionException;
use Override;
use PHPUnit\Framework\TestCase;

final class CallHandleTest extends TestCase
{

	public function testCouldNotReflect(): void
	{
		$instance = new class {

			use CallHandle;

			public function __construct()
			{
				$this->__handler = new class implements Handler
				{

					#[Override]
					public function handle(Call $call): mixed
					{
						return null;
					}

				};
			}

			public function foo(): void
			{
				$args = [];
				$this->__handle('bar', $args);
			}

		};

		$this->expectException(ReflectionException::class);
		$this->expectExceptionMessage('Could not reflect class@anonymous');
		$instance->foo();
	}

}