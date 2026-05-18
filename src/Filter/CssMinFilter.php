<?php
declare(strict_types=1);

namespace WebLoader\Filter;

use tubalmartin\CssMin\Minifier;
use WebLoader\Compiler;

readonly class CssMinFilter
{
	public function __construct(
		private bool $ignoreMinified = false,
	)
	{
	}


	public function __invoke(string $code, Compiler $compiler, string $file = ''): string
	{
		if ($this->ignoreMinified === true && str_ends_with($file, '.min.css')) {
			return $code;
		}

		$minifier = new Minifier;
		return $minifier->run($code);
	}
}
