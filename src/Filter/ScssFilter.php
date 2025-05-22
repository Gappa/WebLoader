<?php

declare(strict_types=1);

namespace WebLoader\Filter;

use WebLoader\Compiler;
use ScssPhp\ScssPhp\Compiler as ScssCompiler;

final class ScssFilter
{

	private function getCompiler(): ScssCompiler
	{
		return new ScssCompiler;
	}


	public function __invoke(string $code, Compiler $loader, string $file): string
	{
		if (pathinfo($file, PATHINFO_EXTENSION) === 'scss') {
			$compiler = $this->getCompiler();
			$compiler->setImportPaths([pathinfo($file, PATHINFO_DIRNAME) . '/']);
			$result = $compiler->compileString($code);
			return $result->getCss();
		}

		return $code;
	}
}
