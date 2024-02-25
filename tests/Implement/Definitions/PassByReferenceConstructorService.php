<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

abstract class PassByReferenceConstructorService
{

	function __construct(&$value)
	{
	}

}