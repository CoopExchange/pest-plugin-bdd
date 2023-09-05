<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\BackgroundNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Output\OutputInterface;
use Vmeretail\PestPluginBdd\Models\PestScenario;
use Vmeretail\PestPluginBdd\Models\PestStep;

final class GherkinProcessor
{
    private GherkinParser $gherkinParser;
    private PestCreator $pestCreator;
    private PestParser $pestParser;

    private OutputHandler $outputHandler;

    private FileHandler $fileHandler;

    private int $errors = 0;
    public function __construct(private readonly OutputInterface $output)
    {
        $this->gherkinParser = new GherkinParser();
        $this->pestCreator = new PestCreator($output);
        $this->pestParser = new PestParser();
        $this->outputHandler = new OutputHandler($output);
        $this->fileHandler = new FileHandler($output);
    }

    public function checkFeaturesHaveTestFiles(bool $createTests = false): int
    {
        $featureFilesArray = $this->fileHandler->getFeatureFiles();

        $this->outputHandler->checkingAllFeatureFilesHaveCorrespondingTestFiles(count($featureFilesArray));

        foreach($featureFilesArray as $featureFileName) {

            $this->processFeatureFile($featureFileName, $createTests);

        }

        return $this->errors;

    }
    private function processFeatureFile(string $featureFilename, bool $createTests)
    {
        $testFilename = $this->fileHandler->getTestFilename($featureFilename);

        $featureFileContents = @file_get_contents($featureFilename, true);

        $featureName = $this->gherkinParser->featureName($featureFileContents);

        if($this->fileHandler->checkTestFileExists($testFilename) === FALSE)
        {

            $this->outputHandler->testFileDoesNotExist($testFilename, $featureName);
            $this->errors++;

            if($createTests === true) {
                $this->pestCreator->createTestFile($testFilename, $featureFileContents);
            }

        } else {

            $this->outputHandler->testFileExists($testFilename, $featureName);
            $this->processExistingTestFile($featureFileContents, $testFilename);

        }

    }

    public function processExistingTestFile(string $featureFileContents, string $testFilename): void
    {
        $featureObject = $this->gherkinParser->gherkin($featureFileContents);

        $this->updateTestFileDescription(
            $testFilename,
            $featureObject->getDescription(),
            $featureObject->getLine()
        );

        if ($featureObject->getBackground() instanceof BackgroundNode) {
            $this->processScenario(
                $featureObject->getBackground(),
                $testFilename,
                $this->pestParser->getFeatureEndLineNumber($this->fileHandler->getTestFile($testFilename))
            );
        }

        foreach($featureObject->getScenarios() as $scenarioObject) {
            $this->processScenario(
                $scenarioObject,
                $testFilename,
                $this->pestParser->getFeatureEndLineNumber($this->fileHandler->getTestFile($testFilename))
            );
        }

    }

    private function updateTestFileDescription(string $testFilename, string $featureDescription, int $lineNumber) : void
    {
        $testFileContents = $this->fileHandler->getTestFile($testFilename);
        $describeDescription = $this->pestParser->getDescribeDescription($testFileContents);

        $testFileLines = $this->fileHandler->openTestFile($testFilename);
        $testFileLines = $this->pestParser->removeExistingDescriptionFromPestFile($describeDescription, $testFileLines);


        if(!is_null($featureDescription)) {

            $testFileLines->splice(($lineNumber+1), 0, $this->pestCreator->writeDescribeDescription($featureDescription));
        }

        $this->fileHandler->savePestFile($testFilename, $testFileLines);
    }

    private function compareFunctionScenarioToPestScenario($testFilename, $scenarioLikeTitle, $pestScenario, $scenarioLikeObject)
    {
        $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

        $this->outputHandler->scenarioIsInTest($scenarioLikeTitle, $testFilename);

        $scenarioBeginLineNumber = $pestScenario->startLine;
        $scenarioEndLineNumber = $pestScenario->endLine;

        if ($scenarioLikeObject instanceof OutlineNode) {

            $editedTestFileLines->put($scenarioBeginLineNumber-1, $this->pestCreator->writeItOpen($scenarioLikeObject));
            $editedTestFileLines = $this->pestParser->deleteExistingDataset($editedTestFileLines, $scenarioEndLineNumber);
            $editedTestFileLines->splice($scenarioEndLineNumber, 0, $this->pestCreator->createOutlineExampleTable($scenarioLikeObject));

        }

        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);

        $this->checkForMissingSteps($scenarioLikeObject->getSteps(), $pestScenario->steps, $scenarioLikeTitle, $testFilename, $pestScenario->endLine);

    }

    private function addMissingPestScenario($testFilename, $scenarioLikeTitle, $scenarioLikeObject, $featureEndLineNumber)
    {
        $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

        $this->outputHandler->scenarioIsNotInTest($scenarioLikeTitle, $testFilename);
        $this->errors++;

        $tempAddition = new Collection();
        $tempAddition->push($this->pestCreator->writeItOpen($scenarioLikeObject));

        foreach ($scenarioLikeObject->getSteps() as $scenarioStepObject) {

            if(!$scenarioLikeObject instanceof BackgroundNode) {
                $stepArguments = $scenarioStepObject->getArguments();
            }

            $tempAddition->push(
                $this->pestCreator->writeStep($testFilename, $scenarioLikeTitle, $scenarioStepObject->getText(), $stepArguments)
            );
        }

        $tempAddition->push($this->pestCreator->writeItClose($scenarioLikeObject));

        if ($scenarioLikeObject instanceof OutlineNode) {
            $tempAddition->push($this->pestCreator->createOutlineExampleTable($scenarioLikeObject));
        }

        $editedTestFileLines->splice(($featureEndLineNumber-1), 0, $tempAddition);

        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);
    }

    private function processScenario($scenarioLikeObject, $testFilename, $featureEndLineNumber)
    {

        // TODO: Get rid of this
        if(is_null($scenarioLikeObject->getTitle())) {
            $scenarioLikeTitle = 'beforeEach';
        } else {
            $scenarioLikeTitle = $scenarioLikeObject->getTitle();
        }

        $pestScenario = $this->pestParser->getScenarios($this->fileHandler->getTestFile($testFilename))
            ->filter(function ($item) use ($scenarioLikeTitle) {
            return $item->name == $scenarioLikeTitle;
        })
            ->first();

        // There is an equivalent scenario (it or beforeEach) in the Pest file
        if ($pestScenario instanceof PestScenario) {

            $this->compareFunctionScenarioToPestScenario($testFilename, $scenarioLikeTitle, $pestScenario, $scenarioLikeObject);

        } else {

            $this->addMissingPestScenario($testFilename, $scenarioLikeTitle, $scenarioLikeObject, $featureEndLineNumber);

        }

        return null;
    }

    private function rewriteStepOpeningLine($testFilename, $startLine, $requiredStepName, $parameterString)
    {
        $editedTestFileLines1 = $this->fileHandler->openTestFile($testFilename);
        $editedTestFileLines1[($startLine)-1] = chr(9).chr(9) . $requiredStepName . '('.$parameterString.');'.PHP_EOL;
        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines1);
    }

    private function rewriteStepData($testFilename, $y, $stepArguments)
    {
        $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

        $editedTestFileLines = $this->pestParser->removeExistingDataFromStep($y, $editedTestFileLines);
        //$stepArgumentsData = $this->pestCreator->convertStepArgumentToString($stepArguments);
        //$data[] = $stepArgumentsData;
        //array_splice($r, $y, 0, $data);
        $editedTestFileLines->splice($y, 0, $this->pestCreator->convertStepArgumentToString($stepArguments));

        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);
    }

    private function addMissingPestStep($testFilename, $scenarioTitle, $scenarioStepObject, $endLine)
    {
        $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);
        $result = $this->pestCreator->writeStep($testFilename, $scenarioTitle, $scenarioStepObject->getText(), $scenarioStepObject->getArguments());
        array_splice($editedTestFileLines, ($endLine+1), 0, $result);
        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);
    }

    private function checkForMissingPestStep($scenarioStepObject, $testFilename, $scenarioTitle, $pestSteps, $endLine)
    {
        $requiredStepNameArray = $this->pestCreator->calculateRequiredStepName($scenarioStepObject->getText(), $testFilename, $scenarioTitle);
        $requiredStepName = $requiredStepNameArray[0];
        $parameterString = $requiredStepNameArray[1];
        $parameterFields = $requiredStepNameArray[2];
        $uniqueIdentifier = $requiredStepNameArray[3];

        // Check if step exists in the pest test file, if not, create it
        $pestStep = $pestSteps->filter(function ($item) use ($requiredStepName) {
            return $item->name == $requiredStepName;
        })->first();

        //ray('KK1', $requiredStepName, $scenarioStepObject, $pestStep, $requiredStepNameArray, $pestSteps, $uniqueIdentifier, $endLine);

        if($pestStep instanceof PestStep) {

            // The step exists in the test file
            $this->outputHandler->stepIsInTest($scenarioStepObject->getText(), $testFilename);

            // Does it have any parameters? If so, rewrite the opening line (including the parameters)
            if(str_contains($requiredStepName, 'parameter')) {
                $this->rewriteStepOpeningLine($testFilename, $pestStep->startLine, $requiredStepName, $parameterString);
            }

            // Rewrite the step data if it has any
            $y = $pestStep->functionStartLine+1;
            $stepArguments = $scenarioStepObject->getArguments();
            if(array_key_exists(0, $stepArguments)) {
                $this->rewriteStepData($testFilename, $y, $stepArguments);
            }

        } else {

            // The step doesn't exist in the test file
            $this->outputHandler->stepIsNotInTest($scenarioStepObject->getText(), $testFilename);
            $this->errors++;

            $this->addMissingPestStep($testFilename, $scenarioTitle, $scenarioStepObject, $endLine);

        }
    }

    private function checkForMissingSteps($scenarioSteps, $pestSteps, $scenarioTitle, string $testFilename, int $endLine)
    {

        foreach ($scenarioSteps as $scenarioStepObject) {

            $this->checkForMissingPestStep($scenarioStepObject, $testFilename, $scenarioTitle, $pestSteps, $endLine);

        }

        // TODO: Loop through all steps in test file and find matching steps in feature - any missing add a comment above in the test file - TODO remove?
        return null;

    }

}
