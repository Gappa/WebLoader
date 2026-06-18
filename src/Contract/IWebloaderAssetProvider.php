<?php
declare(strict_types=1);

namespace WebLoader\Contract;

use Nette\DI\CompilerExtension;

interface IWebloaderAssetProvider
{
	/** @return array<string, array<mixed>> */
	public function getWebloaderAssets(): array;
}
