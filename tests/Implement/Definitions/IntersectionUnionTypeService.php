<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

use Countable;
use Traversable;

interface IntersectionUnionTypeService
{

	function stringOrInt(string|int $stringOrInt, Countable&Traversable $countableTraversable): string|int;

	function countableTraversable(Countable|Traversable $stringAndInt): Countable&Traversable;

	function nullable((Countable&Traversable)|null $countableTraversable): (Countable&Traversable)|null;

}