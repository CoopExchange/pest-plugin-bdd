<?php

namespace CoopExchange\PestPluginBdd;

use Behat\Gherkin\Node\BackgroundNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Output\OutputInterface;
use CoopExchange\PestPluginBdd\Models\PestScenario;
use CoopExchange\PestPluginBdd\Models\PestStep;

final class GherkinProcessor
{
    private GherkinParser $gherkinParser;
    private PestCreator $pestCreator;

    private OutputHandler $outputHandler;

    private FileHandler $fileHandler;

    private int $errors = 0;

    public function __construct(OutputInterface $output)
    {
        $this->gherkinParser = new GherkinParser();
        $this->pestCreator = new PestCreator($output);
        $this->outputHandler = new OutputHandler($output);
        $this->fileHandler = new FileHandler($output);
    }

    public function checkFeaturesHaveTestFiles(bool $createTests = false): int
    {
        $featureFilesArray = $this->fileHandler->getFeatureFiles();

        $this->outputHandler->checkingAllFeatureFilesHaveCorrespondingTestFiles(count($featureFilesArray));

        foreach ($featureFilesArray as $featureFileName) {

            $this->checkFeatureFileHasTestFile($featureFileName, $createTests);

        }

        return $this->errors;

    }

    public function checkFeatureFileHasTestFile(string $featureFilename, bool $createTests): void
    {
        //$featureFilename = 'tmp/TestFeature.feature';
        $testFilename = $this->fileHandler->getTestFilename($featureFilename);

        // TODO: Move this to fileHandler
        $featureFileContents = file_get_contents($featureFilename, true);

        if ($featureFileContents === false) {
            $this->outputHandler->testFileDoesNotExist($testFilename, '');
            $this->errors++;
            return;
        }

        $featureName = $this->gherkinParser->featureName($featureFileContents);

        $fileRecentlyCreated = false;

        if (!$this->fileHandler->checkTestFileExists($testFilename)) {

            $this->outputHandler->testFileDoesNotExist($testFilename, $featureName);
            $this->errors++;

            if ($createTests === true) {
                $this->pestCreator->createTestFile($testFilename, $featureFileContents);
                $fileRecentlyCreated = true;
            }
        }

        if (!$fileRecentlyCreated && $this->fileHandler->checkTestFileExists($testFilename)) {
            if (!$this->checkTestContainsFeatureVersion(
                newFeatureName: $featureName,
                currentFeatureTestContents: $this->fileHandler->getTestFile($testFilename)
            )) {
                $this->pestCreator->addUpdatedFeatureToTestFile(featureFileContents: $featureFileContents, testFileName: $testFilename);
            }
        }

    }

    private function checkTestContainsFeatureVersion(string $newFeatureName, string $currentFeatureTestContents): bool
    {
        $pestParser = new PestParser();
        return $pestParser->getDescribeNames($currentFeatureTestContents)
            ->contains($newFeatureName);
    }

}
