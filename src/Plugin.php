<?php

declare(strict_types=1);

namespace CoopExchange\PestPluginBdd;

use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    use HandleArguments;

    private bool $createTests = false;

    private FileHandler $fileHandler;
    private OutputHandler $outputHandler;

    private GherkinProcessor $gherkinProcessor;

    public function __construct(OutputInterface $output)
    {
        $this->fileHandler = new FileHandler($output);
        $this->outputHandler = new OutputHandler($output);
        $this->gherkinProcessor = new GherkinProcessor($output);
    }

    public function handleArguments(array $arguments): array
    {
        if (!$this->hasArgument('--bdd', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--create-tests', $arguments)) {
            $this->createTests = true;
        }

        foreach ($arguments as $argument) {
            if (str_contains($argument, '.php') === TRUE) {
                $this->singleFile = $argument;
            }
        }

        $missingFeatureFileCount = $this->fileHandler->checkTestsHaveFeatureFiles();
        $missingTestFileCount = $this->gherkinProcessor->checkFeaturesHaveTestFiles($this->createTests);
        $this->outputHandler->errorsToBeFixed(($missingFeatureFileCount + $missingTestFileCount));

        exit(0);
    }

}
