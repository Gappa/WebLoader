<?php
declare(strict_types=1);

namespace WebLoader\Test\Filter;

use PHPUnit\Framework\TestCase;
use WebLoader\Compiler;
use WebLoader\DefaultOutputNamingConvention;
use WebLoader\FileCollection;
use WebLoader\Filter\LessFilter;

class LessFilterTest extends TestCase
{
	private LessFilter $filter;
	private Compiler $compiler;


	protected function setUp(): void
	{
		$this->filter = new LessFilter();

		$files = new FileCollection(__DIR__ . '/../fixtures');
		@mkdir($outputDir = __DIR__ . '/../temp/');
		$this->compiler = new Compiler($files, new DefaultOutputNamingConvention(), $outputDir, 'test');
	}


	public function testReplace(): void
	{
		$file = __DIR__ . '/../fixtures/style.less';
		$minified = $this->filter->__invoke(
			(string) file_get_contents($file),
			$this->compiler,
			$file
		);

		$this->assertSame(file_get_contents(__DIR__ . '/../fixtures/style.less.expected'), $minified);
	}
}
