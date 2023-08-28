<?php

declare(strict_types=1);

namespace Vmeretail\PestPluginBdd;

use Symfony\Component\Console\Output\OutputInterface;

final class FileHandler
{
    private PestCreator $pestCreator;
    private GherkinProcessor $gherkinProcessor;
    private GherkinParser $gherkinParser;
    private readonly OutputHandler $outputHandler;

    private int $errors = 0;

    private const BDD_PATH = 'tests/Feature/';

    public function __construct(private readonly OutputInterface $output)
    {
        $this->pestCreator = new PestCreator();
        $this->gherkinProcessor = new GherkinProcessor($output);
        $this->gherkinParser = new GherkinParser();
        $this->outputHandler = new OutputHandler($output);
    }

    private function getTestFilename($featureFilename)
    {
        return str_replace('.feature', '.php', $featureFilename);
    }

    private function processFeatureFile(string $featureFilename, bool $createTests)
    {
        $testFilename = $this->getTestFilename($featureFilename);

        $featureFileContents = @file_get_contents($featureFilename, true);
        $testFileContents = @file_get_contents($testFilename, true);

        $featureName = $this->gherkinParser->featureName($featureFileContents);

        if($this->checkTestFileExists($testFilename) === FALSE)
        {

            $this->outputHandler->testDoesNotExist($testFilename, $featureName);
            $this->errors++;

            if($createTests === true) {
                $this->pestCreator->createTestFile($testFilename, $featureFileContents);
            }

        } else {

            $this->outputHandler->testExists($testFilename, $featureName);
            $this->processTestFile($testFilename, $featureFileContents, $testFileContents);

        }

    }

    private function processTestFile(string $testFilename, string $featureFileContents, string $testFileContents) : void
    {

        $this->gherkinProcessor->processFeatureScenarios($featureFileContents, $testFilename);
    }

    public function checkFeaturesHaveTestFiles(bool $createTests = false): int
    {
        $featureFilesArray = $this->getFeatureFiles();

        $this->outputHandler->checkingAllFeatureFilesHaveCorrespondingTestFiles(count($featureFilesArray));

        foreach($featureFilesArray as $featureFileName) {

            $this->processFeatureFile($featureFileName, $createTests);

        }

        return $this->errors;

    }

    public function getFeatureFiles(): array
    {
        return $this->getFilteredListofFiles($this->findFiles(), '.feature');
    }

    public function checkTestFileExists(string $featureFilename): bool
    {
        $testFilename = str_replace('.feature', '.php', $featureFilename);

        return file_exists($testFilename);
    }

    public function findFiles() : array
    {
        $directory = new \RecursiveDirectoryIterator(self::BDD_PATH);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        foreach ($iterator as $info) {
            $files[] = $info->getPathname();
        }

        return $files;
    }

    public function getFilteredListofFiles(array $fileList, string $filter) : array
    {
        $filesArray = array_filter($fileList, function ($var) use($filter)
        {
            return (stripos($var, $filter) !== false);
        });

        // need to reset array
        return array_values($filesArray);
    }

    public function getTestFiles(): array
    {
        return $this->getFilteredListofFiles($this->findFiles(), '.php');
    }

    public function checkFeatureFileExists(string $testFilename): bool
    {
        $featureFilename = str_replace('.php', '.feature', $testFilename);

        return file_exists($featureFilename);
    }

    public function checkTestsHaveFeatureFiles(): int
    {
        $testFilesArray = $this->getTestFiles();

        $this->outputHandler->checkingAllTestFilesHaveCorrespondingFeatureFiles(count($testFilesArray));

        foreach($testFilesArray as $testFilename) {

            $featureFilename = str_replace('.php', '.feature', $testFilename);

            if($this->checkFeatureFileExists($testFilename) === FALSE)
            {
                $this->outputHandler->featureDoesNotExist($featureFilename);
                $this->errors++;
            } else {
                $this->outputHandler->featureExists($featureFilename);
            }

        }

        return $this->errors;

    }

}
