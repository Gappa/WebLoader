<?php

declare(strict_types=1);

namespace WebLoader\Nette;

use Nette\DI\Container;
use Nette\Http\IRequest;
use WebLoader\Compiler;
use WebLoader\Contract\IOutputNamingConvention;
use WebLoader\DefaultOutputNamingConvention;

class LoaderFactory
{
	/** @param array<string> $tempPaths */
	public function __construct(
		private array $tempPaths,
		private string $extensionName,
		private IRequest $httpRequest,
		private Container $diContainer,
	)
	{
	}


	/** @var array<string, true> */
	private array $renderedBatches = [];


	public function markBatchAsRendered(string $batchName): void
	{
		$this->renderedBatches[$batchName] = true;
	}


	public function isBatchRendered(string $batchName): bool
	{
		bdump($this->renderedBatches);
		return isset($this->renderedBatches[$batchName]);
	}


	private function getCompiler(string $name, string $type): Compiler
	{
		$serviceName = $this->extensionName . '.' . $type . ucfirst($name) . 'Compiler';

		/** @var Compiler $compiler */
		$compiler = $this->diContainer->getService($serviceName);

		return $compiler;
	}


	public function createCssLoader(string $name, bool $appendLastModified = true): CssLoader
	{
		$compiler = $this->getCompiler($name, 'css');
		$this->modifyConvention($compiler->getOutputNamingConvention(), $name);

		return new CssLoader(
			$compiler,
			$this,
			$this->formatTempPath($name, $compiler->isAbsoluteUrl()),
			$appendLastModified,
		);
	}


	public function createJavaScriptLoader(string $name, bool $appendLastModified = true): JavaScriptLoader
	{
		$compiler = $this->getCompiler($name, 'js');
		$this->modifyConvention($compiler->getOutputNamingConvention(), $name);

		return new JavaScriptLoader(
			$compiler,
			$this,
			$this->formatTempPath($name, $compiler->isAbsoluteUrl()),
			$appendLastModified,
		);
	}


	private function formatTempPath(string $name, bool $absoluteUrl = false): string
	{
		$lName = strtolower($name);
		$tempPath = $this->tempPaths[$lName] ?? Extension::DEFAULT_TEMP_PATH;
		$method = $absoluteUrl ? 'getBaseUrl' : 'getBasePath';
		return rtrim($this->httpRequest->getUrl()->withoutUserInfo()->{$method}(), '/') . '/' . $tempPath;
	}


	private function modifyConvention(IOutputNamingConvention $convention, string $name): void
	{
		if ($convention instanceof DefaultOutputNamingConvention) {
			$convention->setPrefix($name . '-');
		}
	}
}
