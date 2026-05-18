<?php
declare(strict_types=1);

namespace WebLoader\Test\Filter;

use PHPUnit\Framework\TestCase;
use WebLoader\Compiler;
use WebLoader\DefaultOutputNamingConvention;
use WebLoader\FileCollection;
use WebLoader\Filter\ScssFilter;
use WebLoader\Filter\VariablesFilter;

class ScssFilterTest extends TestCase
{
	private ScssFilter $filter;
	private Compiler $compiler;


	protected function setUp(): void
	{
		$this->filter = new ScssFilter();

		$files = new FileCollection(__DIR__ . '/../fixtures');
		@mkdir($outputDir = __DIR__ . '/../temp/');
		$this->compiler = new Compiler($files, new DefaultOutputNamingConvention(), $outputDir);
	}


	public function testReplace(): void
	{
		$file = __DIR__ . '/../fixtures/style.scss';
		$less = $this->filter->__invoke(
			(string) file_get_contents($file),
			$this->compiler,
			$file
		);
		$this->assertSame(file_get_contents(__DIR__ . '/../fixtures/style.scss.expected'), $less);
	}


	public function testImportAbsolutePath(): void
	{
		$file = __DIR__ . '/../fixtures/styleAbsolute.scss';
		$filter = new VariablesFilter([
			'fixturesAbsolutePath' => (string) realpath(__DIR__ . '/../fixtures'),
		]);
		$code = (string) file_get_contents($file);
		$filtered = $filter($code);
		$less = $this->filter->__invoke($filtered, $this->compiler, $file);
		$this->assertSame(file_get_contents(__DIR__ . '/../fixtures/styleAbsolute.scss.expected'), $less);
	}
}
