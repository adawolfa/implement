<?php declare(strict_types=1);

namespace Implement\Cache;

use Adawolfa\Implement\Cache\FileCache;
use Nette\PhpGenerator\PhpFile;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{

	protected function setUp(): void
	{
		parent::setUp();

		foreach (glob(__DIR__ . '/../../temp/*') as $file) {
			unlink($file);
		}
	}

	public function testFileCache(): void
	{
		$cache = new FileCache(__DIR__ . '/../../temp');
		$cache->load('FileFoo');

		$file = new PhpFile;
		$file->addClass('FileFoo');

		$this->assertFalse(class_exists('FileFoo'));
		$cache->write('FileFoo', $file);
		$this->assertFalse(class_exists('FileFoo'));

		$cache->load('FileFoo');
		$this->assertTrue(class_exists('FileFoo'));

		$this->assertFalse(function_exists('stop'));
		file_put_contents(__DIR__ . '/../../temp/FileFoo.8e09a601.php', '<?php stop();');
		$cache->load('FileFoo');
	}

}