<?php declare(strict_types=1);

namespace Adawolfa\Implement\Cache;

use Adawolfa\Implement\RuntimeException;

final class FileCacheException extends RuntimeException
{

	public function __construct(string $message, public readonly string $filename)
	{
		parent::__construct($message);
	}

}