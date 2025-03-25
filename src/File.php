<?php

declare(strict_types=1);

namespace WebLoader;

use SplFileInfo;

final class File
{
	private SplFileInfo $file;

	/** @var array<int|string, string> */
	private array $sourceFiles;


	/** @param array<int|string, string> $sourceFiles */
	public function __construct(
		string $path,
		array $sourceFiles,
	) {
		$this->file = new SplFileInfo($path);
		$this->sourceFiles = $sourceFiles;
	}


	public function getFileName(): string
	{
		return $this->file->getBasename();
	}


	public function getPath(): string
	{
		return $this->file->getPathname();
	}


	public function getLastModified(): int
	{
		return $this->file->getMTime();
	}


	/** @return array<int|string, string> */
	public function getSourceFiles(): array
	{
		return $this->sourceFiles;
	}
}
