<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement;

use Adawolfa\Implement\Cache;
use Adawolfa\Implement\Generator;
use Nette\PhpGenerator\PhpFile;
use Override;
use PHPUnit\Framework\TestCase as TC;

abstract class TestCase extends TC
{

	protected readonly Generator $generator;

	protected readonly StackHandler $handler;

	protected function setUp(): void
	{
		$this->generator = new Generator($this->createGeneratorCache());
		$this->handler   = new StackHandler;
	}

	protected function createGeneratorCache(): Cache
	{
		return new class implements Cache {

			#[Override]
			public function load(string $class): void
			{
			}

			#[Override]
			public function write(string $class, PhpFile $file): void
			{
				$filename = $this->formatFileName($class);
				@mkdir(dirname($filename), recursive: true);
				file_put_contents($filename, (string) $file);
				include_once($filename);
			}

			private function formatFileName(string $class): string
			{
				return sprintf(
					__DIR__ . '/../implementations/%s.php',
					substr($class, strrpos($class, '\\') + 1),
				);
			}

		};
	}

	protected function assertPostConditions(): void
	{
		$this->assertFalse($this->handler->hasCalls());
		$this->assertFalse($this->handler->awaitsCall());
	}

	/**
	 * @template T
	 * @param class-string<T> $classOrInterface
	 * @return T
	 */
	protected function create(string $classOrInterface, mixed ...$args): object
	{
		return $this->generator->generate($classOrInterface)->construct($this->handler, ...$args);
	}

}