<?php

declare(strict_types=1);

namespace WebLoader\Contract;

/**
 * @author Jan Marek
 */
interface IFileCollection
{
	public function getRoot(): string;

	/** @return list<string> */
	public function getFiles(): array;

	/** @return list<string> */
	public function getWatchFiles(): array;
}
