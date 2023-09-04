<?php

declare(strict_types=1);

namespace Vmeretail\PestPluginBdd;

use Symfony\Component\Console\Output\OutputInterface;

final class FileHandler
{
    private readonly OutputHandler $outputHandler;

    private int $errors = 0;

    private const BDD_PATH = 'tests/Feature/';

    public function __construct(private readonly OutputInterface $output)
    {
        $this->outputHandler = new OutputHandler($output);
    }

    public function getTestFilename($featureFilename)
    {
        return str_replace('.feature', '.php', $featureFilename);
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

    public function getTestFile(string $testFilename) : string
    {
        return file_get_contents($testFilename);
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

    public function savePestFile(string $testFilename, array $updatedLines) : void
    {
        $stringVersionOfArray = implode("", $updatedLines);
        file_put_contents($testFilename, $stringVersionOfArray);
    }

    public function openTestFile(string $testFilename) : array
    {
        return file($testFilename);
    }

}
