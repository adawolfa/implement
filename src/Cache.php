<?php declare(strict_types=1);

namespace Adawolfa\Implement;

use Nette\PhpGenerator\PhpFile;

interface Cache
{

	public function load(string $class): void;

	public function write(string $class, PhpFile $file): void;

}