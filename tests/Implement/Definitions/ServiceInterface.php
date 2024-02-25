<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement\Definitions;

/**
 * Service interface.
 */
#[Attribute('class')]
interface ServiceInterface
{

	/**
	 * Method comment.
	 */
	#[Attribute('method')]
	public function method();

	public function voidMethodWithArgs(int $a, string $b, array $c, self $d): void;

	public function arrayMethodWithVarArgs(int $a, string ...$b): array;

	public function nullableWithNullableArg(?string $a): ?string;

}