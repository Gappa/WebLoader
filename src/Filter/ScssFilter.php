<?php

declare(strict_types=1);

namespace WebLoader\Filter;

use ScssPhp\ScssPhp\Compiler as ScssCompiler;
use WebLoader\Compiler;

/**
 * Scss CSS filter
 *
 * @author Roman Matěna
 * @license MIT
 */
class ScssFilter
{
	public function __construct(private ?ScssCompiler $sc = null)
	{
	}


	private function getScssC(): ScssCompiler
	{
		// lazy loading
		if (empty($this->sc)) {
			$this->sc = new ScssCompiler;
		}

		return $this->sc;
	}


	public function __invoke(string $code, Compiler $loader, string $file): string
	{
		if (pathinfo($file, PATHINFO_EXTENSION) === 'scss') {

			$paths = [];

			$cwd = getcwd();

			if ($cwd === false) {
				return $code;
			}

			$this->getScssC()->setImportPaths($this->getImportPaths($file));
			$result = $this->getScssC()->compileString($code);
			return $result->getCss();
		}

		return $code;
	}


	/**
	 * @return list<string>
	 */
	private function getImportPaths(string $file): array
	{
		$paths = [];

		$cwd = getcwd();

		if ($cwd !== false) {
			$paths[] = $cwd;
		}

		$paths[] = pathinfo($file, PATHINFO_DIRNAME) . '/';

		return $paths;
	}
}
