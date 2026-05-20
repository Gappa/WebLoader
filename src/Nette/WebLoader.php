<?php

declare(strict_types=1);

namespace WebLoader\Nette;

use Nette\Application\UI\Control;
use Nette\Utils\Html;
use WebLoader\Compiler;
use WebLoader\Enum\RenderMode;
use WebLoader\File;
use WebLoader\FileCollection;

/**
 * Web loader
 *
 * @author Jan Marek
 * @license MIT
 */
abstract class WebLoader extends Control
{
	private RenderMode $renderMode = RenderMode::LINK;

	public function __construct(
		private Compiler $compiler,
		private string $tempPath,
		private readonly bool $appendLastModified
	) {
	}


	public function getCompiler(): Compiler
	{
		return $this->compiler;
	}


	public function setCompiler(Compiler $compiler): void
	{
		$this->compiler = $compiler;
	}


	public function getTempPath(): string
	{
		return $this->tempPath;
	}


	public function setTempPath(string $tempPath): void
	{
		$this->tempPath = $tempPath;
	}


	/**
	 * Get html element including generated content
	 */
	abstract public function getElement(File $file): Html;


	abstract public function getInlineElement(File $file): Html;


	public function setRenderMode(RenderMode $renderMode): void
	{
		$this->renderMode = $renderMode;
	}


	protected function getUrl(File $file): string
	{
		return $this->getGeneratedFilePath($file);
	}


	/**
	 * Generate compiled file(s) and render link(s)
	 */
	public function render(): void
	{
		$hasArgs = func_num_args() > 0;

		if ($hasArgs) {
			$backup = $this->compiler->getFileCollection();
			$newFiles = new FileCollection($backup->getRoot());
			$newFiles->addFiles(func_get_args());
			$this->compiler->setFileCollection($newFiles);
		}

		$file = $this->compiler->generate();		

		if ($file === null) {
			return;
		}

		$output = match ($this->renderMode) {
			RenderMode::URL => $this->getUrl($file),
			RenderMode::LINK => $this->getElement($file),
			RenderMode::INLINE => $this->getInlineElement($file),
		};

		echo $output, PHP_EOL;

		if ($hasArgs) {
			$this->compiler->setFileCollection($backup);
		}
	}


	public function renderInline(): void
	{
		$this->setRenderMode(RenderMode::INLINE);
		$this->render();
	}


	public function renderUrl(): void
	{
		$this->setRenderMode(renderMode::URL);
		$this->render();
	}


	protected function getGeneratedFilePath(File $file): string
	{
		$path = $this->tempPath . '/' . $file->getFileName();

		if ($this->appendLastModified) {
			$path .= '?' . $file->getLastModified();
		}

		return $path;
	}
}
