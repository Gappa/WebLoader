<?php
declare(strict_types=1);

namespace WebLoader\Test;

use Mockery;
use PHPUnit\Framework\TestCase;
use TypeError;
use WebLoader\Compiler;
use WebLoader\FileNotFoundException;

/**
 * CompilerTest
 *
 * @author Jan Marek
 */
class CompilerTest extends TestCase
{

	private Compiler $object;


	protected function setUp(): void
	{
		$fileCollection = Mockery::mock('WebLoader\IFileCollection');
		$fileCollection->shouldReceive('getFiles')->andReturn([
			__DIR__ . '/fixtures/a.txt',
			__DIR__ . '/fixtures/b.txt',
			__DIR__ . '/fixtures/c.txt',
		]);
		$fileCollection->shouldReceive('getWatchFiles')->andReturn([
			__DIR__ . '/fixtures/a.txt',
			__DIR__ . '/fixtures/b.txt',
			__DIR__ . '/fixtures/c.txt',
		]);

		$convention = Mockery::mock('WebLoader\IOutputNamingConvention');
		$convention->shouldReceive('getFilename')->andReturnUsing(function ($files, $compiler) {
			return 'webloader-' . md5(join(',', $files));
		});

		$this->object = new Compiler($fileCollection, $convention, __DIR__ . '/temp');

		foreach ($this->getTempFiles() as $file) {
			unlink($file);
		}
	}


	private function getTempFiles(): array
	{
		return (array) glob(__DIR__ . '/temp/webloader-*');
	}


	public function testEmptyFiles(): void
	{
		$this->object->setFileCollection(new \WebLoader\FileCollection());

		$ret = $this->object->generate();
		$this->assertCount(0, $ret);
		$this->assertCount(0, $this->getTempFiles());
	}


	public function testSetOutDir(): void
	{
		$this->expectException(FileNotFoundException::class);
		$this->object->setOutputDir('blablabla');
	}


	public function testGeneratingAndFilters(): void
	{
		$this->object->addFileFilter(function ($code) {
			return strrev($code);
		});
		$this->object->addFileFilter(function ($code, Compiler $compiler, $file) {
			return pathinfo($file, PATHINFO_FILENAME) . ':' . $code . ',';
		});
		$this->object->addFilter(function ($code, Compiler $compiler) {
			return '-' . $code;
		});
		$this->object->addFilter(function ($code) {
			return $code . $code;
		});

		$expectedContent = '-' . PHP_EOL . 'a:cba,' . PHP_EOL . 'b:fed,' . PHP_EOL .
			'c:ihg,-' . PHP_EOL . 'a:cba,' . PHP_EOL . 'b:fed,' . PHP_EOL . 'c:ihg,';

		$files = $this->object->generate();

		$this->assertTrue(is_numeric($files[0]->getLastModified()) && $files[0]->getLastModified() > 0, 'Generate does not provide last modified timestamp correctly.');

		$content = file_get_contents($this->object->getOutputDir() . '/' . $files[0]->getFile());

		$this->assertEquals($expectedContent, $content);
	}


	public function testGenerateReturnsSourceFilePaths(): void
	{
		$res = $this->object->generate();
		$this->assertIsArray($res[0]->getSourceFiles());
		$this->assertCount(3, $res[0]->getSourceFiles());
		$this->assertFileExists($res[0]->getSourceFiles()[0]);
	}


	public function testFilters(): void
	{
		$filter = function ($code, Compiler $loader) {
			return $code . $code;
		};
		$this->object->addFilter($filter);
		$this->object->addFilter($filter);
		$this->assertEquals([$filter, $filter], $this->object->getFilters());
	}


	public function testFileFilters(): void
	{
		$filter = function ($code, Compiler $loader, $file = null) {
			return $code . $code;
		};
		$this->object->addFileFilter($filter);
		$this->object->addFileFilter($filter);
		$this->assertEquals([$filter, $filter], $this->object->getFileFilters());
	}


	public function testNonCallableFilter(): void
	{
		$this->expectException(TypeError::class);
		$this->object->addFilter(4);
	}


	public function testNonCallableFileFilter(): void
	{
		$this->expectException(TypeError::class);
		$this->object->addFileFilter(4);
	}
}
