<?php declare(strict_types=1);

namespace Adawolfa\Implement\Cache;

use Adawolfa\Implement\Cache;
use Nette\PhpGenerator\PhpFile;
use Override;

final class MemoryCache implements Cache
{

	#[Override]
	public function load(string $class): void
	{
	}

	#[Override]
	public function write(string $class, PhpFile $file): void
	{
		if (!class_exists($class)) {
			eval(substr((string) $file, 5));
		}
	}

}