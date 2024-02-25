<?php

namespace Tests\Adawolfa\Implement\Definitions;

interface ReferenceService
{

	public function &foo(?string &$foo = null): array;

}