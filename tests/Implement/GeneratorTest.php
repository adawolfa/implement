<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement;

use Adawolfa\Implement\Cache;
use Adawolfa\Implement\Generator;
use Adawolfa\Implement\ReflectionException;
use Adawolfa\Implement\RuntimeException;
use Adawolfa\Implement\ServiceDefinitionException;
use Nette\PhpGenerator\PhpFile;
use Override;
use ReflectionObject;
use stdClass;
use Stringable;
use Tests\Adawolfa\Implement\Definitions\AbstractConstructorService;
use Tests\Adawolfa\Implement\Definitions\AbstractDestructorService;
use Tests\Adawolfa\Implement\Definitions\Attribute;
use Tests\Adawolfa\Implement\Definitions\ConstantDefaultValueService;
use Tests\Adawolfa\Implement\Definitions\EnumService;
use Tests\Adawolfa\Implement\Definitions\FinalConstructorService;
use Tests\Adawolfa\Implement\Definitions\FinalService;
use Tests\Adawolfa\Implement\Definitions\InheritedMethodService;
use Tests\Adawolfa\Implement\Definitions\IntersectionUnionTypeService;
use Tests\Adawolfa\Implement\Definitions\MethodTrait;
use Tests\Adawolfa\Implement\Definitions\NoSemicolonService;
use Tests\Adawolfa\Implement\Definitions\PassByReferenceConstructorService;
use Tests\Adawolfa\Implement\Definitions\PrivateConstructorService;
use Tests\Adawolfa\Implement\Definitions\ReferenceService;
use Tests\Adawolfa\Implement\Definitions\ReservedMethodService;
use Tests\Adawolfa\Implement\Definitions\ServiceInterface;
use Tests\Adawolfa\Implement\Definitions\StaticMethodService;

final class GeneratorTest extends TestCase
{

	public function testGenerateFinalClass(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot create implementation for final');
		$this->generator->generate(FinalService::class);
	}

	public function testGenerateEnum(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot create implementation for enum');
		$this->generator->generate(EnumService::class);
	}

	public function testGenerateTrait(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot create implementation for trait');
		$this->generator->generate(MethodTrait::class);
	}

	public function testGenerateAnonymous(): void
	{
		$instance = new class {
		};
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot create implementation for anonymous class');
		$this->generator->generate($instance::class);
	}

	public function testGenerateMissingType(): void
	{
		$this->expectException(ReflectionException::class);
		$this->expectExceptionMessage('Could not reflect');
		$this->generator->generate('Foo');
	}

	public function testStrictTypes(): void
	{
		$filename = (new ReflectionObject($this->create(ServiceInterface::class)))->getFileName();
		$code     = file_get_contents($filename);
		$this->assertStringContainsString('strict_types', $code);
	}

	public function testNoStrictTypes(): void
	{
		$filename = (new ReflectionObject($this->create(ReferenceService::class)))->getFileName();
		$code     = file_get_contents($filename);
		$this->assertStringNotContainsString('strict_types', $code);
	}

	public function testNoStrictTypesNoSemicolons(): void
	{
		$filename = (new ReflectionObject($this->create(NoSemicolonService::class)))->getFileName();
		$code     = file_get_contents($filename);
		$this->assertStringNotContainsString('strict_types', $code);
	}

	public function testFinalConstructor(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessageMatches('~^Cannot implement .* with final constructor .*::__construct\(\)\.$~');
		$this->generator->generate(FinalConstructorService::class);
	}

	public function testPrivateConstructor(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessageMatches('~^Cannot implement .* with private constructor .*::__construct\(\)\.$~');
		$this->generator->generate(PrivateConstructorService::class);
	}

	public function testAbstractConstructor(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot implement constructor');
		$this->generator->generate(AbstractConstructorService::class);
	}

	public function testAbstractDestructor(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot implement destructor');
		$this->generator->generate(AbstractDestructorService::class);
	}

	public function testPassByReferenceConstructor(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Passed by reference parameters are not supported for constructor');
		$this->generator->generate(PassByReferenceConstructorService::class);
	}

	public function testStaticMethod(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot implement static');
		$this->generator->generate(StaticMethodService::class);
	}

	public function testBadCache(): void
	{
		$generator = new Generator(new class implements Cache {

			#[Override]
			public function load(string $class): void
			{
			}

			#[Override]
			public function write(string $class, PhpFile $file): void
			{
			}

		});

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Failed to load generated implementation for stdClass.');
		$generator->generate(stdClass::class);
	}

	public function testConstantDefaultValue(): void
	{
		$service    = $this->create(ConstantDefaultValueService::class);
		$reflection = new ReflectionObject($service);
		$this->assertTrue($reflection->getMethod('foo')->getParameters()[0]->isDefaultValueConstant());
	}

	public function testOverrides(): void
	{
		$service    = $this->create(ServiceInterface::class);
		$reflection = new ReflectionObject($service);
		$this->assertNotEmpty($reflection->getMethod('method')->getAttributes(Override::class));
	}

	public function testAttributes(): void
	{
		$service    = $this->create(ServiceInterface::class);
		$reflection = new ReflectionObject($service);

		$classAttribute = $reflection->getAttributes()[0]->newInstance();
		$this->assertInstanceOf(Attribute::class, $classAttribute);
		$this->assertSame('class', $classAttribute->foo);

		$methodAttribute = $reflection->getMethod('method')->getAttributes()[1]->newInstance();
		$this->assertInstanceOf(Attribute::class, $methodAttribute);
		$this->assertSame('method', $methodAttribute->foo);
	}

	public function testAttributesInherited(): void
	{
		$service    = $this->create(InheritedMethodService::class);
		$reflection = new ReflectionObject($service);

		$paramAttribute = $reflection->getMethod('traitMethod')->getParameters()[0]->getAttributes()[0]->newInstance();
		$this->assertInstanceOf(Attribute::class, $paramAttribute);
		$this->assertSame('param', $paramAttribute->foo);
	}

	public function testDocComments(): void
	{
		$service    = $this->create(Stringable::class);
		$reflection = new ReflectionObject($service);
		$this->assertSame("/**\n * @internal\n */", str_replace("\r\n", "\n", $reflection->getDocComment()));
		$this->assertFalse($reflection->getMethod('__toString')->getDocComment());

		$service    = $this->create(ServiceInterface::class);
		$reflection = new ReflectionObject($service);
		$this->assertSame("/**\n * Service interface.\n * @internal\n */", str_replace("\r\n", "\n", $reflection->getDocComment()));
		$this->assertSame("/**\n\t * Method comment.\n\t */", str_replace("\r\n", "\n", $reflection->getMethod('method')->getDocComment()));
	}

	public function testReservedMethod(): void
	{
		$this->expectException(ServiceDefinitionException::class);
		$this->expectExceptionMessage('Cannot implement reserved');
		$this->create(ReservedMethodService::class);
	}

	public function testIntersectionUnionTypes(): void
	{
		$this->create(IntersectionUnionTypeService::class);
	}

}