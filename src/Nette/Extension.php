<?php

declare(strict_types=1);

namespace WebLoader\Nette;

use Nette\Bootstrap\Configurator;
use Nette\DI\Compiler;
use Nette\DI\CompilerExtension;
use Nette\DI\ContainerBuilder;
use Nette\Schema\Expect;
use Nette\Schema\Helpers as SchemaHelpers;
use Nette\Schema\Processor;
use Nette\Schema\Schema;
use Nette\Utils\FileInfo;
use Nette\Utils\Finder;
use WebLoader\BatchCollection;
use WebLoader\Compiler as WebloaderCompiler;
use WebLoader\Contract\IWebloaderAssetProvider;
use WebLoader\Exception\BatchAlreadyExistsException;
use WebLoader\Exception\CompilationException;
use WebLoader\Exception\FileNotFoundException;
use WebLoader\FileCollection;
use WebLoader\Nette\Diagnostics\Panel;
use WebLoader\Nette\SymfonyConsole\GenerateCommand;

/**
 * @author Jan Marek
 */
class Extension extends CompilerExtension
{
	public const DEFAULT_TEMP_PATH = 'webtemp';
	public const EXTENSION_NAME = 'webloader';

	private string $appDir;
	private string $wwwDir;
	private bool $debugMode;
	private BatchCollection $batchCollection;


	public function __construct(string $appDir, string $wwwDir, bool $debugMode)
	{
		$this->appDir = $appDir;
		$this->wwwDir = $wwwDir;
		$this->debugMode = $debugMode;
		$this->batchCollection = new BatchCollection;
	}


	private function getJsConfigSchema(bool $useDefaults = false): Schema
	{
		$checkLastModified = $useDefaults ? true : null;
		$debug = $useDefaults ? false : null;
		$sourceDir = $useDefaults ? ($this->wwwDir . '/js') : null;
		$tempDir = $useDefaults ? ($this->wwwDir . '/' . self::DEFAULT_TEMP_PATH) : null;
		$tempPath = $useDefaults ? self::DEFAULT_TEMP_PATH : null;
		$async = $useDefaults ? false : null;
		$defer = $useDefaults ? false : null;
		$absoluteUrl = $useDefaults ? false : null;
		$namingConvention = $useDefaults
			? ('@' . $this->prefix('jsNamingConvention'))
			: null;

		return Expect::structure([
			'checkLastModified' => Expect::bool($checkLastModified),
			'debug' => Expect::bool($debug),
			'sourceDir' => Expect::string($sourceDir),
			'tempDir' => Expect::string($tempDir),
			'tempPath' => Expect::string($tempPath),
			'files' => Expect::array(),
			'watchFiles' => Expect::array(),
			'filters' => Expect::array(),
			'fileFilters' => Expect::array(),
			'async' => Expect::bool($async),
			'defer' => Expect::bool($defer),
			'nonce' => Expect::string()->nullable(),
			'absoluteUrl' => Expect::bool($absoluteUrl),
			'namingConvention' => Expect::string($namingConvention),
		]);
	}


	private function getCssConfigSchema(bool $useDefaults = false): Schema
	{
		$checkLastModified = $useDefaults ? true : null;
		$debug = $useDefaults ? false : null;
		$sourceDir = $useDefaults ? ($this->wwwDir . '/css') : null;
		$tempDir = $useDefaults ? ($this->wwwDir . '/' . self::DEFAULT_TEMP_PATH) : null;
		$tempPath = $useDefaults ? self::DEFAULT_TEMP_PATH : null;
		$async = $useDefaults ? false : null;
		$defer = $useDefaults ? false : null;
		$absoluteUrl = $useDefaults ? false : null;
		$namingConvention = $useDefaults
			? ('@' . $this->prefix('cssNamingConvention'))
			: null;

		return Expect::structure([
			'checkLastModified' => Expect::bool($checkLastModified),
			'debug' => Expect::bool($debug),
			'sourceDir' => Expect::string($sourceDir),
			'tempDir' => Expect::string($tempDir),
			'tempPath' => Expect::string($tempPath),
			'files' => Expect::array(),
			'watchFiles' => Expect::array(),
			'filters' => Expect::array(),
			'fileFilters' => Expect::array(),
			'async' => Expect::bool($async),
			'defer' => Expect::bool($defer),
			'nonce' => Expect::string()->nullable(),
			'absoluteUrl' => Expect::bool($absoluteUrl),
			'namingConvention' => Expect::string($namingConvention),
		]);
	}


	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'jsDefaults' => $this->getJsConfigSchema(true),
			'cssDefaults' => $this->getCssConfigSchema(true),
			'js' => Expect::arrayOf($this->getJsConfigSchema())->nullable(),
			'css' => Expect::arrayOf($this->getCssConfigSchema())->nullable(),
			'debugger' => Expect::bool($this->debugMode),
		]);
	}


	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();
		$config = json_decode((string) json_encode($this->getConfig()), true);

		$builder->addDefinition($this->prefix('cssNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createCssConvention');

		$builder->addDefinition($this->prefix('jsNamingConvention'))
			->setFactory('WebLoader\DefaultOutputNamingConvention::createJsConvention');

		if ($config['debugger']) {
			$builder->addDefinition($this->prefix('tracyPanel'))
				->setType(Panel::class)
				->setArguments([$this->appDir]);
		}

		$builder->parameters['webloader'] = $config;

		$loaderFactoryTempPaths = [];

		$this->extractBatchesFromExtensions();
		$this->extractNormalBatches($config);

		$batchTypes = $this->batchCollection->getBatches();
		foreach ($batchTypes as $type => $batches) {
			foreach ($batches as $name => $batch) {
				$batch = array_filter($batch);
				$batch = SchemaHelpers::merge($batch, $config[$type . 'Defaults']);

				if (!is_array($batch)) {
					throw new CompilationException('Batch config not valid.');
				}

				$this->addWebLoader($builder, $type . ucfirst($name), $batch);
				$loaderFactoryTempPaths[strtolower($name)] = $batch['tempPath'];

				if (!is_dir($batch['tempDir']) || !is_writable($batch['tempDir'])) {
					throw new CompilationException(sprintf("You must create a writable directory '%s'", $batch['tempDir']));
				}
			}
		}

		$builder->addDefinition($this->prefix('factory'))
			->setType(LoaderFactory::class)
			->setArguments([$loaderFactoryTempPaths, $this->name]);

		if (class_exists('Symfony\Component\Console\Command\Command')) {
			$builder->addDefinition($this->prefix('generateCommand'))
				->setType(GenerateCommand::class)
				->addTag('kdyby.console.command');
		}
	}


	/**
	 * @param ContainerBuilder $builder
	 * @param string $name
	 * @param array<int|string, mixed> $config
	 * @return void
	 * @throws FileNotFoundException
	 */
	private function addWebLoader(ContainerBuilder $builder, string $name, array $config): void
	{
		$filesServiceName = $this->prefix($name . 'Files');

		$files = $builder->addDefinition($filesServiceName)
			->setType(FileCollection::class)
			->setArguments([$config['sourceDir']]);

		foreach ($this->findFiles($config['files'], $config['sourceDir']) as $file) {
			$files->addSetup('addFile', [$file]);
		}

		foreach ($this->findFiles($config['watchFiles'], $config['sourceDir']) as $file) {
			$files->addSetup('addWatchFile', [$file]);
		}

		$compiler = $builder->addDefinition($this->prefix($name . 'Compiler'))
			->setType(WebloaderCompiler::class)
			->setArguments([
				'@' . $filesServiceName,
				$config['namingConvention'],
				$config['tempDir'],
			]);

		$compiler
			->addSetup('setAsync', [$config['async']])
			->addSetup('setDefer', [$config['defer']])
			->addSetup('setNonce', [$config['nonce']])
			->addSetup('setAbsoluteUrl', [$config['absoluteUrl']]);

		if ($builder->parameters['webloader']['debugger']) {
			$compiler->addSetup('@' . $this->prefix('tracyPanel') . '::addLoader', [
				$name,
				'@' . $this->prefix($name . 'Compiler'),
			]);
		}

		foreach ($config['filters'] as $filter) {
			$compiler->addSetup('addFilter', [$filter]);
		}

		foreach ($config['fileFilters'] as $filter) {
			$compiler->addSetup('addFileFilter', [$filter]);
		}

		if (isset($config['debug']) && $config['debug']) {
			$compiler->addSetup('enableDebugging');
		}

		$compiler->addSetup('setCheckLastModified', [$config['checkLastModified']]);

		// todo css media
	}


	// I have no clue what this is supposed to do...
	// public function afterCompile(ClassType $class): void
	// {
	// 	$types = $class->getProperty('types');
	// 	if (array_key_exists('webloader\\nette\\loaderfactory', $types)) {
	// 		$types['webloader\\loaderfactory'] = $types['webloader\\nette\\loaderfactory'];
	// 	}
	// 	if (array_key_exists('WebLoader\\Nette\\LoaderFactory', $types)) {
	// 		$types['WebLoader\\LoaderFactory'] = $types['WebLoader\\Nette\\LoaderFactory'];
	// 	}
	//
	// 	$init = $class->methods['initialize'];
	// 	$init->addBody('if (!class_exists(?, ?)) class_alias(?, ?);', ['WebLoader\\LoaderFactory', false, 'WebLoader\\Nette\\LoaderFactory', 'WebLoader\\LoaderFactory']);
	// }


	public function install(Configurator $configurator): void
	{
		$self = $this;
		$configurator->onCompile[] = function ($configurator, Compiler $compiler) use ($self): void {
			$compiler->addExtension($self::EXTENSION_NAME, $self);
		};
	}


	/**
	 * @param array<int|string, mixed> $filesConfig
	 * @param string $sourceDir
	 * @return array<int|string, mixed>
	 * @throws FileNotFoundException
	 */
	private function findFiles(array $filesConfig, string $sourceDir): array
	{
		$normalizedFiles = [];

		/** @var array<string, mixed>|string $file */
		foreach ($filesConfig as $file) {
			// finder support
			if (is_array($file) && isset($file['files']) && (isset($file['in']) || isset($file['from']))) {
				$finder = Finder::findFiles($file['files']);

				if (isset($file['exclude'])) {
					$finder->exclude($file['exclude']);
				}

				if (isset($file['in'])) {
					$finder->in(is_dir($file['in']) ? $file['in'] : $sourceDir . DIRECTORY_SEPARATOR . $file['in']);
				} else {
					$finder->from(is_dir($file['from']) ? $file['from'] : $sourceDir . DIRECTORY_SEPARATOR . $file['from']);
				}

				$foundFilesList = [];
				foreach ($finder as $foundFile) {
					/** @var FileInfo $foundFile */
					$foundFilesList[] = $foundFile->getPathname();
				}

				natsort($foundFilesList);

				/** @var string $foundFilePathname */
				foreach ($foundFilesList as $foundFilePathname) {
					$normalizedFiles[] = $foundFilePathname;
				}

			} else {
				if (is_string($file)) {
					$this->checkFileExists($file, $sourceDir);
					$normalizedFiles[] = $file;
				}
			}
		}

		return $normalizedFiles;
	}


	protected function checkFileExists(string $file, string $sourceDir): void
	{
		if (!$this->fileExists($file)) {
			$tmp = rtrim($sourceDir, '/\\') . DIRECTORY_SEPARATOR . $file;
			if (!$this->fileExists($tmp)) {
				throw new FileNotFoundException(sprintf("Neither '%s' or '%s' was found", $file, $tmp));
			}
		}
	}


	/**
	 * Some servers seem to have problems under cron user with open_basedir restriction when using relative paths
	 */
	protected function fileExists(string $file): bool
	{
		$file = realpath($file);

		if ($file === false) {
			$file = '';
		}

		return file_exists($file);
	}


	private function extractBatchesFromExtensions(): void
	{
		// Extension batches
		/** @var array<IWebloaderAssetProvider> $batchProviders */
		$batchProviders = $this->compiler->getExtensions(IWebloaderAssetProvider::class);

		if (empty($batchProviders)) {
			return;
		}

		$schemaProcessor = new Processor;

		foreach ($batchProviders as $batchProvider) {
			$assets = $batchProvider->getWebloaderAssets();
			$schemaProcessor->process($this->getConfigSchema(), $assets);

			foreach ($assets as $type => $batches) {
				foreach ($batches as $name => $batch) {
					$this->batchCollection->addBatch($type, $name, $batch);
				}
			}
		}
	}


	/**
	 * @param array<string, mixed> $config
	 * @throws BatchAlreadyExistsException
	 */
	private function extractNormalBatches(array $config): void
	{
		foreach (['css', 'js'] as $type) {
			foreach ($config[$type] as $name => $batch) {
				$this->batchCollection->addBatch($type, $name, $batch);
			}
		}
	}
}
