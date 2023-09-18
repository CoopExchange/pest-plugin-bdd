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

            $this->checkFeatureFileHasTestFile($featureFileName, $createTests);

        }

        return $this->errors;

    }
    public function checkFeatureFileHasTestFile(string $featureFilename, bool $createTests)
    {
        //$featureFilename = 'tmp/TestFeature.feature';
        $testFilename = $this->fileHandler->getTestFilename($featureFilename);

        //ray('Q1', $testFilename);

        // TODO: Move this to fileHandler
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

        if (!is_null($featureObject->getDescription())) {
            $this->updateTestFileDescription(
                $testFilename,
                $featureObject->getDescription()
            );
        }

        if ($featureObject->getBackground() instanceof BackgroundNode) {

            $describeDescription = $this->pestParser->getDescribeDescription(file_get_contents($testFilename));
            if(!is_null($describeDescription[2])) {
                $beforeEachStartLine = $describeDescription[2] + 2;
            } else {
                $beforeEachStartLine = $describeDescription[3] + 2;
            }

            ray('BACKGROUND', $featureObject->getBackground(), $featureObject);
            ray('DESCRIBE DESCRIPTION', $this->pestParser->getDescribeDescription(file_get_contents($testFilename)));
            //dd('STOP BACKGROUND');
            $this->processScenario(
                $featureObject->getBackground(),
                $testFilename,
                $beforeEachStartLine
            );
        }

        foreach($featureObject->getScenarios() as $scenarioObject) {
            ray('A1: **processExistingTestFile** EACH SCENARIO:' , $scenarioObject);
            $this->processScenario(
                $scenarioObject,
                $testFilename,
                $this->pestParser->getFeatureEndLineNumber($this->fileHandler->getTestFile($testFilename))
            );
        }

    }

    private function updateTestFileDescription(string $testFilename, string $featureDescription) : void
    {
        $testFileContents = $this->fileHandler->getTestFile($testFilename);
        $describeDescription = $this->pestParser->getDescribeDescription($testFileContents);
        //ray('DESCRIBE DESCRIPTION FROM EXISTING PEST FILE:', $describeDescription);

        $lineNumber = $describeDescription[3];

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
        ray('A3_1 **compareFunctionScenarioToPestScenario** OPENED TEST FILE: ', $editedTestFileLines);

        $this->outputHandler->scenarioIsInTest($scenarioLikeTitle, $testFilename);

        $scenarioBeginLineNumber = $pestScenario->startLine;
        $scenarioEndLineNumber = $pestScenario->endLine;

        if ($scenarioLikeObject instanceof OutlineNode) {

            $editedTestFileLines->put($scenarioBeginLineNumber-1, $this->pestCreator->writeItOpen($scenarioLikeObject));
            $editedTestFileLines = $this->pestParser->deleteExistingDataset($editedTestFileLines, $scenarioEndLineNumber);
            $editedTestFileLines->splice($scenarioEndLineNumber, 0, $this->pestCreator->createOutlineExampleTable($scenarioLikeObject));

        }

        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);
        ray('A3_2 **compareFunctionScenarioToPestScenario** SAVED TEST FILE: ', $editedTestFileLines);

        $this->checkForMissingSteps($scenarioLikeObject->getSteps(), $pestScenario->steps, $scenarioLikeTitle, $testFilename, $pestScenario->endLine);

        $temp = $this->fileHandler->openTestFile($testFilename);
        ray('A3_3 TESTFILE: **compareFunctionScenarioToPestScenario** AFTER CHECK FOR MISSING STEPS: ', $temp);
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
            } else {
                $stepArguments = [];
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

        // TODO: Get rid of this - only for background/beforeEach
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
            ray('A2: **processScenario** FOUND PEST SCENARIO: '. $scenarioLikeTitle);
            $this->compareFunctionScenarioToPestScenario($testFilename, $scenarioLikeTitle, $pestScenario, $scenarioLikeObject);

        } else {
            ray('A2: **processScenario** NOT FOUND PEST SCENARIO: '. $scenarioLikeTitle);
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
        ray('A6_1 **rewriteStepData** TEST FILE TO BEGIN: ', $editedTestFileLines);

        $editedTestFileLines = $this->pestParser->removeExistingDataFromStep($y, $editedTestFileLines);
        ray('A6_2 **rewriteStepData** AFTER REMOVING EXISTING DATA FROM STEP: ', $editedTestFileLines);
        //$stepArgumentsData = $this->pestCreator->convertStepArgumentToString($stepArguments);
        //$data[] = $stepArgumentsData;
        //array_splice($r, $y, 0, $data);
        $editedTestFileLines->splice($y, 0, $this->pestCreator->convertStepArgumentToString($stepArguments));
        ray('A6_3 **rewriteStepData** AFTER SPLICING DATA BACK IN: ', $editedTestFileLines);

        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);
    }

    private function addMissingPestStep($testFilename, $scenarioTitle, $scenarioStepObject, $endLine)
    {
        $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);
        $editedTestFileLines->splice(
            ($endLine+1),
            0,
            $this->pestCreator->writeStep($testFilename, $scenarioTitle, $scenarioStepObject->getText(), $scenarioStepObject->getArguments())
        );

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

        ray('A5_1: **checkForMissingPestStep** ', $requiredStepName, $scenarioStepObject, $pestStep, $requiredStepNameArray, $pestSteps, $uniqueIdentifier, $endLine);

        if($pestStep instanceof PestStep) {

            ray('A5_2 **checkForMissingPestStep** STEP EXISTS IN FILE');

            // The step exists in the test file
            $this->outputHandler->stepIsInTest($scenarioStepObject->getText(), $testFilename);

            // Does it have any parameters? If so, rewrite the opening line (including the parameters)
            if(str_contains($requiredStepName, 'parameter')) {
                $this->rewriteStepOpeningLine($testFilename, $pestStep->startLine, $requiredStepName, $parameterString);
                $temp = $this->fileHandler->openTestFile($testFilename);
                ray('A5_3: **checkForMissingPestStep**  AFTER STEP IN EXISTING FILE: ', $temp);

            }

            $y = $pestStep->functionStartLine+1;
            // Rewrite the step data if it has any
            ray('A5_4: **checkForMissingPestStep**  ABOUT TO REWRITE STEP DATA: y='.$y);

            $stepArguments = $scenarioStepObject->getArguments();
            if(array_key_exists(0, $stepArguments)) {
                ray('A5_4_1 REWRITING STEP DATA: ', $y, $stepArguments);
                $this->rewriteStepData($testFilename, $y, $stepArguments);
            }

            $temp2 = $this->fileHandler->openTestFile($testFilename);
            ray('A5_5: **checkForMissingPestStep**  AFTER REWRITING STEP DATA: ', $temp2);

        } else {

            ray('A5_6 **checkForMissingPestStep** STEP DOES NOT EXIST IN THE PEST FILE SO WILL ADD');

            // The step doesn't exist in the test file
            $this->outputHandler->stepIsNotInTest($scenarioStepObject->getText(), $testFilename);
            $this->errors++;

            $this->addMissingPestStep($testFilename, $scenarioTitle, $scenarioStepObject, $endLine);

            $temp = $this->fileHandler->openTestFile($testFilename);
            ray('A5_7: **checkForMissingPestStep** AFTER MISSING STEP ADDED TO EXISTING FILE: ', $temp);

        }
    }

    private function checkForMissingSteps($scenarioSteps, $pestSteps, $scenarioTitle, string $testFilename, int $endLine)
    {

        foreach ($scenarioSteps as $scenarioStepObject) {
            ray('A4_1: **checkForMissingSteps** ABOUT TO CHECK FOR MISSING PEST STEP', $scenarioStepObject);
            $this->checkForMissingPestStep($scenarioStepObject, $testFilename, $scenarioTitle, $pestSteps, $endLine);

            $temp = $this->fileHandler->openTestFile($testFilename);
            ray('A4_2: **checkForMissingSteps** AFTER CHECK FOR MISSING PEST STEP: ', $temp);

        }

        // TODO: Loop through all steps in test file and find matching steps in feature - any missing add a comment above in the test file - TODO remove?
        return null;

    }

}
