<?php

declare(strict_types=1);

namespace WebLoader\Nette\SymfonyConsole;

use Nette\DI\Container;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use WebLoader\Compiler;

/**
 * Generate Command
 */
#[AsCommand(name: 'webloader:generate', description: 'Generate files.')]
class GenerateCommand extends Command
{

	/** @var Compiler[] */
	private array $compilers = [];


	public function __construct(Container $container)
	{
		parent::__construct();

		$compilers = $container->findByType(Compiler::class);
		foreach ($compilers as $compilerName) {
			$this->compilers[$compilerName] = $container->getService($compilerName);
		}
	}


	protected function configure(): void
	{
		$this->addOption('force', 'f', InputOption::VALUE_NONE, 'Generate if not modified.');
	}


	protected function execute(InputInterface $input, OutputInterface $output): int
	{
		$noFiles = true;
		foreach ($this->compilers as $compiler) {
			$file = $compiler->generate();
			if (null !== $file) {
				$output->writeln($file->getFileName());
				$noFiles = false;
			}
		}

		if ($noFiles) {
			$output->writeln('No files generated.');
			return 1;
		}

		return 0;
	}
}
