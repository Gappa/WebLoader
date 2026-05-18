<?php
declare(strict_types=1);

namespace WebLoader\Filter;

use JShrink\Minifier;
use WebLoader\Compiler;

readonly class JsMinFilter
{
	public function __construct(
		private bool $ignoreMinified = false,
	)
	{
	}


	public function __invoke(string $code, Compiler $compiler, string $file = ''): string
	{
		if ($this->ignoreMinified === true && str_ends_with($file, '.min.js')) {
			return $code;
		}

		$result = Minifier::minify($code);
		return (string) $result;
	}
}
