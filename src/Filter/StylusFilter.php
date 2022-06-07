<?php

declare(strict_types=1);

namespace WebLoader\Filter;

use WebLoader\Compiler;

/**
 * Stylus filter
 *
 * @author Patrik Votoček
 * @license MIT
 */
class StylusFilter
{
	public bool $compress = false;
	public bool $includeCss = false;


	public function __construct(private string $bin = 'stylus')
	{
	}


	public function __invoke(string $code, Compiler $loader, ?string $file = null): string
	{
		$file = (string) $file;

		if (pathinfo($file, PATHINFO_EXTENSION) === 'styl') {
			$cmd = $this->bin . ($this->compress ? ' -c' : '') . ($this->includeCss ? ' --include-css' : '') . ' -I ' . pathinfo($file, PATHINFO_DIRNAME);
			try {
				$code = Process::run($cmd, $code);
			} catch (\RuntimeException $e) {
				throw new \WebLoader\Exception\WebLoaderException('Stylus Filter Error', 0, $e);
			}
		}

		return (string) $code;
	}
}
