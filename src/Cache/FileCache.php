<?php declare(strict_types=1);

namespace Adawolfa\Implement\Cache;

use Adawolfa\Implement\Cache;
use Adawolfa\Implement\RuntimeException;
use Nette\PhpGenerator\PhpFile;
use Override;
use Random\RandomException;

final class FileCache implements Cache
{

	private string $folder;

	public function __construct(?string $folder = null)
	{
		$this->folder = $folder ?? sys_get_temp_dir();
	}

	#[Override]
	public function load(string $class): void
	{
		@include_once($this->getClassFile($class));
	}

	#[Override]
	public function write(string $class, PhpFile $file): void
	{
		$filename = $this->getClassFile($class);
		@mkdir(dirname($filename), recursive: true);

		$pid = getmypid();
		assert($pid !== false);

		try {
			$temp = $filename . '.' . sprintf('%05d%06d', $pid, random_int(0, 999999));
		} catch (RandomException $e) {
			throw new RuntimeException('Could not generate temporary filename.', previous: $e);
		}

		if (file_put_contents($temp, (string) $file, LOCK_EX) === false) {
			throw new FileCacheException('Could not write cache file.', $temp);
		}

		if (rename($temp, $filename) === false) {
			throw new FileCacheException('Could not move temporary cache file.', $filename);
		}
	}

	private function getClassFile(string $class): string
	{
		return sprintf(
			'%s/%s.%08x.php',
			$this->folder,
			str_contains($class, '\\') ? substr($class, strrpos($class, '\\') + 1) : $class,
			crc32($class),
		);
	}

}