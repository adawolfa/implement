<?php declare(strict_types=1);

namespace Adawolfa\Implement;

use Adawolfa\Implement\Cache\MemoryCache;
use Adawolfa\Implement\Internal\CallHandle;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Method;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpNamespace;
use Override;
use PhpToken;
use ReflectionClass;
use ReflectionException as PHPReflectionException;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionType;
use ReflectionUnionType;

/**
 * Service implementation generator.
 */
class Generator
{

	private readonly Cache $cache;

	public function __construct(?Cache $cache = null)
	{
		$this->cache = $cache ?? new MemoryCache;
	}

	/**
	 * Generates an implementation for given class or interface.
	 * @template T of object
	 * @param class-string<T> $classOrInterface class or interface to create implementation for
	 * @return Implementation<T>                   generated service implementation
	 */
	final public function generate(string $classOrInterface): Implementation
	{
		$implementationClassName = $this->formatImplementationClassName($classOrInterface);
		$this->cache->load($implementationClassName);

		if (class_exists($implementationClassName)) {
			assert(is_subclass_of($implementationClassName, $classOrInterface));
			return $this->createImplementation($implementationClassName);
		}

		return $this->doGenerate($classOrInterface, $implementationClassName);
	}

	/**
	 * Called before the generated file gets loaded, use for additional modifications to the code.
	 */
	protected function afterGenerate(string $classOrInterface, PhpFile $file): void
	{
	}

	/**
	 * @template T of object
	 * @param class-string<T> $classOrInterface
	 * @return Implementation<T>
	 */
	private function doGenerate(string $classOrInterface, string $implementationClassName): Implementation
	{
		$reflection = $this->reflectClassOrInterface($classOrInterface);
		$file       = $this->doGenerateClassFile($reflection, $implementationClassName);

		$this->afterGenerate($classOrInterface, $file);

		$this->cache->write($implementationClassName, $file);
		$this->cache->load($implementationClassName);

		if (!class_exists($implementationClassName)) {
			throw new RuntimeException("Failed to load generated implementation for $classOrInterface.");
		}

		assert(is_subclass_of($implementationClassName, $classOrInterface));
		return $this->createImplementation($implementationClassName);
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 */
	private function doGenerateClassFile(ReflectionClass $reflection, string $implementationClassName): PhpFile
	{
		$file = new PhpFile;
		$file->setStrictTypes($this->checkUsesStrictTypes($reflection));

		$lsp = strrpos($implementationClassName, '\\');
		assert($lsp !== false);
		$implementationNamespace = substr($implementationClassName, 0, $lsp);
		$implementationBaseClass = substr($implementationClassName, $lsp + 1);

		$namespace = $file->addNamespace($implementationNamespace);
		$this->doGenerateClass($namespace, $reflection, $implementationBaseClass);

		return $file;
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 */
	private function doGenerateClass(
		PhpNamespace    $namespace,
		ReflectionClass $reflection,
		string          $implementationBaseClass
	): void
	{
		$class = $namespace->addClass($implementationBaseClass);
		$class->setFinal();
		$class->addTrait(CallHandle::class);

		$comment = $reflection->getDocComment();

		if ($comment !== false) {
			$comment = preg_replace('#^\s*\* ?#m', '', trim(trim(trim($comment), '/*')));
			$comment .= "\n@internal";
			$class->setComment($comment);
		} else {
			$class->setComment('@internal');
		}

		if ($reflection->isInterface()) {
			$class->addImplement($reflection->name);
		} else {
			$class->setExtends($reflection->name);
		}

		$this->doGenerateConstructor($class, $reflection);

		foreach ($this->getMethodsToImplement($reflection) as $method) {
			$this->doGenerateMethod($class, $method);
		}

		$this->copyAttributes($reflection, $class);
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 */
	private function doGenerateConstructor(ClassType $class, ReflectionClass $reflection): void
	{
		$constructor = $class->addMethod('__construct');
		$constructor
			->addParameter('__handler')
			->setType(Handler::class);

		$constructor->addBody('$this->__handler = $__handler;');

		$constructorReflection = $reflection->getConstructor();

		if ($constructorReflection === null) {
			return;
		}

		if ($constructorReflection->isFinal()) {
			throw ServiceDefinitionException::cannotImplementConstructor('final', $reflection, $constructorReflection);
		} elseif ($constructorReflection->isPrivate()) {
			throw ServiceDefinitionException::cannotImplementConstructor('private', $reflection, $constructorReflection);
		}

		$parametersToPass = [];

		foreach ($constructorReflection->getParameters() as $parameterReflection) {
			$this->doGenerateParameter($constructor, $constructorReflection, $parameterReflection);
			$parametersToPass[] = '$' . $parameterReflection->name;
		}

		$constructor->addBody(sprintf('parent::__construct(%s);', implode(', ', $parametersToPass)));

		$this->copyAttributes($constructorReflection, $constructor);
	}

	private function doGenerateMethod(ClassType $class, ReflectionMethod $methodReflection): void
	{
		if ($methodReflection->isStatic()) {
			throw ServiceDefinitionException::cannotImplementMethod('static', $methodReflection);
		} elseif (in_array($methodReflection->name, ['__handle', '__handleByRef', '__prepareCall', '__doesHandlerReturnByReference'], true)) {
			throw ServiceDefinitionException::cannotImplementMethod('reserved', $methodReflection);
		} elseif ($methodReflection->isConstructor()) {
			throw ServiceDefinitionException::cannotImplementMethod('constructor', $methodReflection);
		} elseif ($methodReflection->isDestructor()) {
			throw ServiceDefinitionException::cannotImplementMethod('destructor', $methodReflection);
		}

		$method = $class->addMethod($methodReflection->name);
		$method->addAttribute(Override::class);

		if ($methodReflection->isProtected()) {
			$method->setVisibility('protected');
		}

		$comment = $methodReflection->getDocComment();

		if ($comment !== false) {
			$method->setComment(preg_replace('#^\s*\* ?#m', '', trim(trim(trim($comment), '/*'))));
		}

		if ($methodReflection->hasReturnType()) {

			$returnType = $methodReflection->getReturnType();

			if ($returnType !== null) {
				$method->setReturnType($this->formatType($returnType));
			}

			if ($returnType instanceof ReflectionNamedType && $returnType->allowsNull()) {
				$method->setReturnNullable();
			}

		}

		if ($methodReflection->returnsReference()) {
			$method->setReturnReference();
		}

		$passedByReference = false;

		foreach ($methodReflection->getParameters() as $parameterReflection) {
			$this->doGenerateParameter($method, $methodReflection, $parameterReflection);
			$passedByReference = $passedByReference || $parameterReflection->isPassedByReference();
		}

		$method->addBody('$arguments = func_get_args();');

		if ($passedByReference) {
			$method->addBody('try {');
		}

		if ($methodReflection->returnsReference()) {

			$method->addBody(<<<'PHP'
				if ($this->__doesHandlerReturnByReference()) {
					return $this->__handleByRef(__FUNCTION__, $arguments);
				} else {
					$ret = $this->__handle(__FUNCTION__, $arguments);
					return $ret;
				}
			PHP);

		} else {

			if (!$methodReflection->hasReturnType()
				|| !$methodReflection->getReturnType() instanceof ReflectionNamedType
				|| $methodReflection->getReturnType()->getName() !== 'void') {
				$method->addBody('return');
			}

			$method->addBody(
				'$this->__doesHandlerReturnByReference()'
				. ' ? $this->__handleByRef(__FUNCTION__, $arguments)'
				. ' : $this->__handle(__FUNCTION__, $arguments);',
			);
		}

		if ($passedByReference) {

			$method->addBody('} finally {');

			foreach ($methodReflection->getParameters() as $parameterReflection) {
				$method->addBody(<<<PHP
					if (array_key_exists({$parameterReflection->getPosition()}, \$arguments)) {
						\$$parameterReflection->name = \$arguments[{$parameterReflection->getPosition()}];
					}
				PHP);
			}

			$method->addBody('}');

		}

		$this->copyAttributes($methodReflection, $method);
	}

	private function doGenerateParameter(
		Method              $method,
		ReflectionMethod    $methodReflection,
		ReflectionParameter $parameterReflection,
	): void
	{
		$parameter = $method->addParameter($parameterReflection->name);

		if ($parameterReflection->hasType()) {

			if ($parameterReflection->getType() instanceof ReflectionNamedType
				&& $parameterReflection->getType()->getName() === 'self') {
				$parameter->setType($methodReflection->getDeclaringClass()->name);
			} elseif ($parameterReflection->getType() !== null) {
				$parameter->setType($this->formatType($parameterReflection->getType()));
			}

		}

		if ($parameterReflection->getType() instanceof ReflectionNamedType && $parameterReflection->allowsNull()) {
			$parameter->setNullable();
		}

		if ($parameterReflection->isVariadic()) {
			$method->setVariadic();
		}

		if ($parameterReflection->isPassedByReference()) {

			if ($methodReflection->isConstructor()) {
				throw new ServiceDefinitionException(
					"Passed by reference parameters are not supported for constructor "
					. "{$methodReflection->getDeclaringClass()->name}::__construct().",
				);
			}

			$parameter->setReference();

		}

		if ($parameterReflection->isDefaultValueAvailable()) {

			if ($parameterReflection->isDefaultValueConstant()) {

				try {

					$constantName = $parameterReflection->getDefaultValueConstantName();
					assert($constantName !== null);
					assert($parameterReflection->getDeclaringClass() !== null);

					if (str_starts_with($constantName, 'self::')) {
						$constantName = '\\' . $parameterReflection->getDeclaringClass()->name . substr($constantName, 4);
					}

					$parameter->setDefaultValue(new Literal($constantName));

				} catch (PHPReflectionException $e) {
					throw new ReflectionException("Could not obtain default value constant name.", previous: $e);
				}

			} elseif (is_scalar($parameterReflection->getDefaultValue()) || $parameterReflection->getDefaultValue() === null) {
				$parameter->setDefaultValue(new Literal((string) ($parameterReflection->getDefaultValue() ?? 'null')));
			}

		} elseif ($parameterReflection->isOptional()) {
			$parameter->setDefaultValue(new Literal('null'));
		}

		$this->copyAttributes($parameterReflection, $parameter);
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 * @return ReflectionMethod[]
	 */
	private function getMethodsToImplement(ReflectionClass $reflection): array
	{
		$methods = [];

		foreach ($reflection->getInterfaces() as $interface) {

			foreach ($this->getMethodsToImplement($interface) as $name => $parentMethod) {
				$methods[$name] = $parentMethod;
			}

		}

		$parentClass = $reflection->getParentClass();

		if ($parentClass !== false) {

			foreach ($this->getMethodsToImplement($parentClass) as $name => $parentMethod) {
				$methods[$name] = $parentMethod;
			}

		}

		foreach ($reflection->getTraits() as $trait) {

			foreach ($this->getMethodsToImplement($trait) as $name => $parentMethod) {
				$methods[$name] = $parentMethod;
			}

		}

		foreach ($reflection->getMethods() as $method) {

			if ($reflection->isInterface() || $method->isAbstract()) {
				$methods[$method->name] = $method;
			} else {
				unset($methods[$method->name]);
			}

		}

		return $methods;
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T>|ReflectionMethod|ReflectionParameter $source
	 * @param ClassType|Method|Parameter                              $target
	 */
	private function copyAttributes(
		ReflectionClass|ReflectionMethod|ReflectionParameter $source,
		ClassType|Method|Parameter                           $target,
	): void
	{
		foreach ($source->getAttributes() as $attribute) {
			$target->addAttribute($attribute->getName(), $attribute->getArguments());
		}
	}

	/**
	 * @template T of object
	 * @param ReflectionClass<T> $reflection
	 */
	private function checkUsesStrictTypes(ReflectionClass $reflection): bool
	{
		if (!$reflection->isUserDefined()) {
			return false;
		}

		$filename = $reflection->getFileName();

		if ($filename === false) {
			throw new RuntimeException("Could not determine strict-typing setting for $reflection->name.");
		}

		$code = file_get_contents($filename);

		if ($code === false) {
			throw new ReflectionException("Could not read $filename.");
		}

		/** @var array<int, PhpToken|null> $tokens */
		$tokens = PhpToken::tokenize($code);

		for ($i = 0; isset($tokens[$i]); $i++) {

			if ($tokens[$i]->getTokenName() === ';') {
				return false;
			}

			if (!$tokens[$i]->is(T_DECLARE)) {
				continue;
			}

			for ($i++; isset($tokens[$i]) && $tokens[$i]->text !== ')'; $i++) {

				if (!$tokens[$i]->is(T_STRING) || $tokens[$i]->text !== 'strict_types') {
					continue;
				}

				while ($tokens[$i]?->is(T_LNUMBER) === false) {
					$i++;
				}

				if ($tokens[$i]?->is(T_LNUMBER)) {
					return $tokens[$i]->text === '1';
				}

			}

		}

		return false;
	}

	private function formatType(ReflectionIntersectionType|ReflectionUnionType|ReflectionNamedType $type): string
	{
		if ($type instanceof ReflectionNamedType) {
			return $type->getName();
		}

		if ($type instanceof ReflectionIntersectionType) {

			return implode('&', array_map(
				function (ReflectionType $type): string {

					if ($type instanceof ReflectionUnionType) {
						return '(' . $this->formatType($type) . ')';
					}

					return $this->formatType($type);

				},
				$type->getTypes(),
			));

		}

		$nullable = false;

		foreach ($type->getTypes() as $intersectionType) {

			if ($intersectionType instanceof ReflectionNamedType && $intersectionType->getName() === 'null') {
				$nullable = true;
				break;
			}

		}

		return implode('|', array_map(
			function (ReflectionType $type) use ($nullable): string {

				if ($nullable && !$type instanceof ReflectionNamedType) {
					return '(' . $this->formatType($type) . ')';
				}

				return $this->formatType($type);

			},
			$type->getTypes(),
		));
	}

	/**
	 * @template T of object
	 * @param class-string<T> $classOrInterface
	 * @return ReflectionClass<T>
	 */
	private function reflectClassOrInterface(string $classOrInterface): ReflectionClass
	{
		try {
			$reflection = new ReflectionClass($classOrInterface);
		} catch (PHPReflectionException $exception) {
			throw new ReflectionException("Could not reflect $classOrInterface.", previous: $exception);
		}

		if ($reflection->isEnum()) {
			throw ServiceDefinitionException::cannotCreateImplementationFor('enum', $reflection);
		}

		if ($reflection->isFinal()) {
			throw ServiceDefinitionException::cannotCreateImplementationFor('final', $reflection);
		}

		if ($reflection->isAnonymous()) {
			throw ServiceDefinitionException::cannotCreateImplementationFor('anonymous class', $reflection);
		}

		if ($reflection->isTrait()) {
			throw ServiceDefinitionException::cannotCreateImplementationFor('trait', $reflection);
		}

		return $reflection;
	}

	/**
	 * @template T of object
	 * @param class-string<T> $implementationClassName
	 * @return Implementation<T>
	 */
	private function createImplementation(string $implementationClassName): Implementation
	{
		try {
			$reflection = new ReflectionClass($implementationClassName);
		} catch (PHPReflectionException $exception) {
			throw new ReflectionException("Could not reflect the implementation $implementationClassName.", previous: $exception);
		}

		return new Implementation($reflection);
	}

	private function formatImplementationClassName(string $classOrInterface): string
	{
		$self = static::class;

		if (str_contains($self, '@')) {
			$reflection = new ReflectionClass($this);
			assert($reflection->getFileName() !== false);
			assert($reflection->getStartLine() !== false);
			assert($reflection->getEndLine() !== false);
			$self = sprintf('Anonymous_%08x', crc32(serialize([
				$reflection->getFileName(),
				$reflection->getStartLine(),
				$reflection->getEndLine(),
			])));
		}

		return sprintf('__GENERATED__\\%s\\%s', $self, $classOrInterface);
	}

}