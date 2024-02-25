<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement;

use Adawolfa\Implement\Call\Arguments;
use Adawolfa\Implement\Call\CannotModifyArgumentException;
use Adawolfa\Implement\Call\UnknownParameterException;
use Adawolfa\Implement\LogicException;
use Adawolfa\Implement\RuntimeException;
use ReflectionClass;
use ReflectionException;
use Tests\Adawolfa\Implement\Definitions\ArgumentsService;

final class ArgumentsTest extends TestCase
{

	/**
	 * @throws ReflectionException
	 */
	public function testGet(): void
	{
		$reflection = (new ReflectionClass(ArgumentsService::class))->getMethod('foo');
		$values     = [1, 'b', null];
		$arguments  = new Arguments($values, $reflection);
		$this->assertCount(3, $arguments);
		$this->assertSame(1, $arguments[0]);
		$this->assertSame(1, $arguments['a']);
		$this->assertSame('b', $arguments[1]);
		$this->assertSame('b', $arguments['b']);
		$this->assertNull($arguments[2]);
		$this->assertNull($arguments['c']);
		$this->assertFalse($arguments[3]);
		$this->assertFalse($arguments['d']);
		$this->assertTrue(isset($arguments[0]));
		$this->assertTrue(isset($arguments['a']));
		$this->assertFalse(isset($arguments[2]));
		$this->assertFalse(isset($arguments['c']));
		$this->assertFalse(isset($arguments[4]));
		$this->assertFalse(isset($arguments['e']));
	}

	/**
	 * @throws ReflectionException
	 */
	public function testSet(): void
	{
		$reflection   = (new ReflectionClass(ArgumentsService::class))->getMethod('foo');
		$values       = [1, 'b'];
		$arguments    = new Arguments($values, $reflection);
		$arguments[2] = 'foo';
		$this->assertSame('foo', $values[2]);
		$arguments['c'] = null;
		$this->assertNull($values[2]);
	}

	/**
	 * @throws ReflectionException
	 */
	public function testGetNonExistentPosition(): void
	{
		$reflection = (new ReflectionClass(ArgumentsService::class))->getMethod('foo');
		$values     = [1, 'b'];
		$arguments  = new Arguments($values, $reflection);
		$this->expectException(UnknownParameterException::class);
		$this->expectExceptionMessage('Unknown parameter #4 for');
		$arguments[4];
	}

	/**
	 * @throws ReflectionException
	 */
	public function testGetNonExistentNamed(): void
	{
		$reflection = (new ReflectionClass(ArgumentsService::class))->getMethod('foo');
		$values     = [1, 'b'];
		$arguments  = new Arguments($values, $reflection);
		$this->expectException(UnknownParameterException::class);
		$this->expectExceptionMessage('Unknown parameter $e for');
		$arguments['e'];
	}

	/**
	 * @throws ReflectionException
	 */
	public function testModifyArgument(): void
	{
		$reflection = (new ReflectionClass(ArgumentsService::class))->getMethod('foo');
		$values     = [1, 'b'];
		$arguments  = new Arguments($values, $reflection);
		$this->expectException(CannotModifyArgumentException::class);
		$this->expectExceptionMessageMatches('~^Cannot modify argument \$a of .*because it is not passed by reference\.$~');
		$arguments[0] = 2;
	}

	/**
	 * @throws ReflectionException
	 */
	public function testUnsetArgument(): void
	{
		$reflection = (new ReflectionClass(ArgumentsService::class))->getMethod('foo');
		$values     = [1, 'b'];
		$arguments  = new Arguments($values, $reflection);
		$this->expectException(LogicException::class);
		$this->expectExceptionMessage('Cannot unset an argument.');
		unset($arguments[2]);
	}

	/**
	 * @throws ReflectionException
	 */
	public function testDefaultValueNotAvailable(): void
	{
		$reflection = (new ReflectionClass(ArgumentsService::class))->getMethod('bar');
		$values     = [];
		$arguments  = new Arguments($values, $reflection);
		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('No default value available for $a of');
		$arguments[0];
	}

}