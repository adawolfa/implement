<?php declare(strict_types=1);

namespace Adawolfa\Implement;

/**
 * Service call handler.
 */
interface Handler
{

	/**
	 * Handles a service method call.
	 * @param  Call $call
	 * @return mixed
	 */
	public function handle(Call $call): mixed;

}