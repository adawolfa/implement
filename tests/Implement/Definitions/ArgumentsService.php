<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

interface ArgumentsService
{

	public function foo(int $a, string $b, mixed &$c, ?bool $d = false);

	public function bar($a);

}