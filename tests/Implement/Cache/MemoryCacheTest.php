<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Cache;

use Adawolfa\Implement\Cache\MemoryCache;
use Nette\PhpGenerator\PhpFile;
use PHPUnit\Framework\TestCase;

final class MemoryCacheTest extends TestCase
{

	public function testMemoryCache(): void
	{
		$cache = new MemoryCache;
		$cache->load('MemoryFoo');

		$file = new PhpFile;
		$file->addClass('MemoryFoo');

		$this->assertFalse(class_exists('MemoryFoo'));
		$cache->write('MemoryFoo', $file);
		$this->assertTrue(class_exists('MemoryFoo'));

		$cache->write('MemoryFoo', $file);
	}

}