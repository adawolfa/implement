<?php declare(strict_types=1);

namespace Tests\Adawolfa\Implement;

use Adawolfa\Implement\Call;
use Adawolfa\Implement\Handler;
use Adawolfa\Implement\LogicException;
use Override;

final class StackHandler implements Handler
{

	private array $returns = [];

	/** @var Call[] */
	private array $calls = [];

	#[Override]
	public function handle(Call $call): mixed
	{
		if (count($this->returns) === 0) {
			throw new LogicException('No calls have been expected.');
		}

		$this->calls[] = $call;

		return array_shift($this->returns);
	}

	public function return(mixed $value = null): void
	{
		$this->returns[] = $value;
	}

	public function pop(): Call
	{
		if (!$this->hasCalls()) {
			throw new LogicException('No calls have been made.');
		}

		return array_pop($this->calls);
	}

	public function hasCalls(): bool
	{
		return count($this->calls) > 0;
	}

	public function awaitsCall(): bool
	{
		return count($this->returns) > 0;
	}

}
