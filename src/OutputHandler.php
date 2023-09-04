<?php

namespace Vmeretail\PestPluginBdd;

use Symfony\Component\Console\Output\OutputInterface;

class OutputHandler
{
    public function __construct(private readonly OutputInterface $output)
    {

    }

    public function testFileDoesNotExist(string $testFilename, string $featureName) : void
    {
        $this->output->writeln('<bg=red;options=bold> TEST </> ' . $testFilename . ' does not exist for Feature: ' . $featureName);
    }

    public function testFileExists(string $testFilename, string $featureName) : void
    {
        $this->output->writeln('<bg=green;options=bold> TEST </> ' . $testFilename . ' exists for Feature: ' . $featureName);
    }

    public function featureDoesNotExist(string $featureFilename) : void
    {
        $this->output->writeln('<bg=red;options=bold> FEATURE </> ' . $featureFilename . ' does NOT exist');
    }

    public function featureExists(string $featureFilename) : void
    {
        $this->output->writeln('<bg=green;options=bold> FEATURE </> ' . $featureFilename . ' does exist');
    }

    public function checkingAllFeatureFilesHaveCorrespondingTestFiles(int $featureFilesArrayCount) : void
    {
        $this->output->writeln('<info>Processing ' . $featureFilesArrayCount . ' feature files to ensure they have corresponding test files</info>');
    }

    public function checkingAllTestFilesHaveCorrespondingFeatureFiles(int $testFilesArrayCount) : void
    {
        $this->output->writeln('<info>Processing ' . $testFilesArrayCount . ' test files to ensure they have corresponding feature files</info>');
    }

    public function errorsToBeFixed(int $errorCount) : void
    {
        $this->output->writeln('');
        $this->output->writeln('There are <error>' . $errorCount . '</error> errors to be fixed');
    }

    public function scenarioIsInTest(string $scenarioTitle, string $testFilename) : void
    {
        $this->output->writeln('<bg=green;options=bold> SCENARIO </> <bg=gray>' . $scenarioTitle . '</> IS in the '.$testFilename.' test file');
    }

    public function scenarioIsNotInTest(string $scenarioTitle, string $testFilename) : void
    {
        $this->output->writeln('<bg=red;options=bold> SCENARIO </> "' . $scenarioTitle . '" is NOT in the ' . $testFilename . ' test file');
    }

    public function stepIsInTest(string $stepText, string $testFilename) : void
    {
        $this->output->writeln('<bg=green;options=bold> STEP </> <bg=gray>' . $stepText . '</> IS in the '.$testFilename.' test file');
    }

    public function stepIsNotInTest(string $stepText, string $testFilename) : void
    {
        $this->output->writeln('<bg=red;options=bold> STEP </> <bg=gray>' . $stepText . '</> is NOT in the ' . $testFilename . ' test file');
    }

}
