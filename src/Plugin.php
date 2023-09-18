<?php

declare(strict_types=1);

namespace Vmeretail\PestPluginBdd;

// use Pest\Contracts\Plugins\AddsOutput;
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

    private bool $amendTests = false;

    private string $singleFile;

    private FileHandler $fileHandler;
    private OutputHandler $outputHandler;

    private GherkinProcessor $gherkinProcessor;

    public function __construct(private readonly OutputInterface $output)
    {
        $this->fileHandler = new FileHandler($output);
        $this->outputHandler = new OutputHandler($output);
        $this->gherkinProcessor = new GherkinProcessor($output);
    }

    public function handleArguments(array $arguments): array
    {
        //ray('ARGUMENTS', $arguments);

        if (! $this->hasArgument('--bdd', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--create-tests', $arguments)) {
            $this->createTests = true;
        }

        if ($this->hasArgument('--amend-tests', $arguments)) {
            $this->amendTests = true;
        }

        foreach($arguments as $argument) {
            if(str_contains($argument, '.php') === TRUE) {
                $this->singleFile = $argument;
            }
        }

        if (!is_null($this->singleFile) === TRUE) {
            $featureFile = str_replace('.php', '.feature', $this->singleFile);

            $this->gherkinProcessor->checkFeatureFileHasTestFile($featureFile, $this->createTests);
        } else {
            $missingFeatureFileCount = $this->fileHandler->checkTestsHaveFeatureFiles();
            $missingTestFileCount = $this->gherkinProcessor->checkFeaturesHaveTestFiles($this->createTests);
            $this->outputHandler->errorsToBeFixed(($missingFeatureFileCount + $missingTestFileCount));
        }

        exit(0);
    }

}
