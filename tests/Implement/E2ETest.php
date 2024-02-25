<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement;

use Adawolfa\Implement\Call;
use Adawolfa\Implement\Generator;
use Adawolfa\Implement\Handler;
use ArrayAccess;
use Nette\PhpGenerator\PhpFile;
use Override;
use ReflectionObject;
use ReturnTypeWillChange;
use Stringable;
use Tests\Adawolfa\Implement\Definitions\ConstructorService;
use Tests\Adawolfa\Implement\Definitions\InheritedMethodService;
use Tests\Adawolfa\Implement\Definitions\MethodImplementedService;
use Tests\Adawolfa\Implement\Definitions\ParentConstructorService;
use Tests\Adawolfa\Implement\Definitions\ProtectedMethodService;
use Tests\Adawolfa\Implement\Definitions\ReferenceService;
use Tests\Adawolfa\Implement\Definitions\ServiceInterface;
use Tests\Adawolfa\Implement\Definitions\TraitMethodImplementedService;

final class E2ETest extends TestCase
{

	public function testSimpleInterface(): void
	{
		$service = $this->create(ServiceInterface::class);

		$this->handler->return();
		$service->method();
		$call = $this->handler->pop();

		$this->assertSame('method', $call->method->name);
		$this->assertSame([], $call->arguments->toArray());
		$this->assertSame($service, $call->service);

		$this->handler->return([1, 2, 3]);
		$this->assertSame([1, 2, 3], $service->arrayMethodWithVarArgs(1, 'foo', 'bar'));
		$call = $this->handler->pop();

		$this->assertSame('arrayMethodWithVarArgs', $call->method->name);
		$this->assertSame([1, 'foo', 'bar'], $call->arguments->toArray());
		$this->assertSame($service, $call->service);

		$this->handler->return('bar');
		$this->assertSame('bar', $service->nullableWithNullableArg('foo'));
		$call = $this->handler->pop();

		$this->assertSame('nullableWithNullableArg', $call->method->name);
		$this->assertSame(['foo'], $call->arguments->toArray());
		$this->assertSame($service, $call->service);

		$this->handler->return();
		$this->assertNull($service->nullableWithNullableArg(null));
		$call = $this->handler->pop();

		$this->assertSame('nullableWithNullableArg', $call->method->name);
		$this->assertSame([null], $call->arguments->toArray());
		$this->assertSame($service, $call->service);

		$this->handler->return();
		$service->voidMethodWithArgs(1, 'foo', ['bar'], $service);
		$call = $this->handler->pop();

		$this->assertSame('voidMethodWithArgs', $call->method->name);
		$this->assertSame([1, 'foo', ['bar'], $service], $call->arguments->toArray());
		$this->assertSame($service, $call->service);
	}

	public function testParentConstructor(): void
	{
		$service = $this->create(ParentConstructorService::class, 'bar', 'foo');
		$this->assertSame('foo', $service->foo);
		$this->assertSame('bar', $service->bar);

		$this->handler->return();
		$service->method();
		$call = $this->handler->pop();

		$this->assertSame('method', $call->method->name);
		$this->assertSame([], $call->arguments->toArray());
		$this->assertSame($service, $call->service);
	}

	public function testConstructor(): void
	{
		$service = $this->create(ConstructorService::class, 'foo', 'bar');
		$this->assertSame('foo', $service->foo);
		$this->assertSame('bar', $service->bar);

		$this->handler->return();
		$service->method();
		$call = $this->handler->pop();

		$this->assertSame('method', $call->method->name);
		$this->assertSame([], $call->arguments->toArray());
		$this->assertSame($service, $call->service);
	}

	public function testMethodImplemented(): void
	{
		$service = $this->create(MethodImplementedService::class);
		$service->method();

		$reflection = new ReflectionObject($service);
		$this->assertSame(
			MethodImplementedService::class,
			$reflection->getMethod('method')->getDeclaringClass()->name,
		);
	}

	public function testMethodTraitImplemented(): void
	{
		$service = $this->create(TraitMethodImplementedService::class);
		$service->method();

		$reflection = new ReflectionObject($service);
		$this->assertSame(
			TraitMethodImplementedService::class,
			$reflection->getMethod('method')->getDeclaringClass()->name,
		);
	}

	public function testBuiltInTypes(): void
	{
		$stringable = $this->create(Stringable::class);
		$this->handler->return('foo');
		$this->assertSame('foo', (string) $stringable);
		$call = $this->handler->pop();
		$this->assertSame('__toString', $call->method->name);
		$this->assertSame([], $call->arguments->toArray());

		$generator = new class ($this->createGeneratorCache()) extends Generator {

			protected function afterGenerate(string $classOrInterface, PhpFile $file): void
			{
				foreach ($file->getClasses() as $class) {

					foreach ($class->getMethods() as $method) {
						$method->addAttribute(ReturnTypeWillChange::class);
					}

				}
			}

		};

		$arrayAccess = $generator->generate(ArrayAccess::class)->construct($this->handler);
		$this->handler->return('foo');
		$this->assertSame('foo', $arrayAccess[5]);
		$call = $this->handler->pop();
		$this->assertSame('offsetGet', $call->method->name);
		$this->assertSame([5], $call->arguments->toArray());
	}

	public function testUndeclaredNamedParameters(): void
	{
		$service = $this->create(ServiceInterface::class);
		$this->handler->return([]);
		$service->arrayMethodWithVarArgs(1, foo: 'foo', bar: 'bar');
		$call = $this->handler->pop();
		$this->assertSame([1], $call->arguments->toArray());

		$service = $this->create(ServiceInterface::class);
		$this->handler->return([]);
		$service->arrayMethodWithVarArgs(1, 'b', 'c', foo: 'foo', bar: 'bar');
		$call = $this->handler->pop();
		$this->assertSame([1, 'b', 'c'], $call->arguments->toArray());
	}

	public function testReturnsReference(): void
	{
		$handler = new class implements Handler {

			public array $foo = ['foo'];

			#[Override]
			public function &handle(Call $call): mixed
			{
				return $this->foo;
			}

		};

		$service = $this->generator
			->generate(ReferenceService::class)
			->construct($handler);

		$service->foo($bar)[0] = 'bar';
		$this->assertSame(['bar'], $handler->foo);
	}

	public function testPassByReference(): void
	{
		$handler = new class implements Handler {

			#[Override]
			public function handle(Call $call): array
			{
				$arguments    = $call->arguments;
				$arguments[0] = 'foo';
				return [];
			}

		};

		$service = $this->generator
			->generate(ReferenceService::class)
			->construct($handler);

		$service->foo($foo);
		$this->assertSame('foo', $foo);
	}

	public function testPassByReferenceNull(): void
	{
		$handler = new class implements Handler {

			#[Override]
			public function handle(Call $call): array
			{
				$arguments    = $call->arguments;
				$arguments[0] = null;
				return [];
			}

		};

		$service = $this->generator
			->generate(ReferenceService::class)
			->construct($handler);

		$foo = 'foo';
		$service->foo($foo);
		$this->assertNull($foo);
	}

	public function testProtectedMethod(): void
	{
		$service = $this->create(ProtectedMethodService::class);
		$this->handler->return();
		$service->bar();
		$this->assertSame('foo', $this->handler->pop()->method->name);
	}

	public function testInheritedMethods(): void
	{
		$service = $this->create(InheritedMethodService::class);

		$this->handler->return();
		$service->parentMethod();
		$this->handler->pop();

		$this->handler->return();
		$service->traitMethod();
		$this->handler->pop();
	}

}