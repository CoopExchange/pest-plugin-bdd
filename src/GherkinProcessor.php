<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\BackgroundNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Console\Output\OutputInterface;

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
        $testFileContents = @file_get_contents($testFilename, true);

        $featureName = $this->gherkinParser->featureName($featureFileContents);

        if($this->fileHandler->checkTestFileExists($testFilename) === FALSE)
        {

            $this->outputHandler->testDoesNotExist($testFilename, $featureName);
            $this->errors++;

            if($createTests === true) {
                $this->pestCreator->createTestFile($testFilename, $featureFileContents);
            }

        } else {

            $this->outputHandler->testExists($testFilename, $featureName);
            $this->processScenarios($featureFileContents, $testFilename);

        }

    }

    public function processScenarios(string $featureFileContents, string $testFilename): void
    {
        $parsedTestFileArray = $this->pestParser->parseTestFileIntoArrays(file_get_contents($testFilename));

        $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

        $featureObject = $this->gherkinParser->gherkin($featureFileContents);
        $featuresArray = $parsedTestFileArray[0];
        $featureEndLineNumber = array_search($featureObject->getTitle(), $featuresArray, true);

        $testFileContents = @file_get_contents($testFilename, true);
        $describeDescription = $this->pestParser->getDescribeDescription($testFileContents);

        $editedTestFileLines = $this->pestParser->removeExistingDescriptionFromPestFile($describeDescription, $editedTestFileLines);

        // Write feature description (including rules) from feature file, if it exists
        if(!is_null($featureObject->getDescription())) {
            $xAddition = $this->pestCreator->writeDescribeDescription($featureObject->getDescription());
            array_splice($editedTestFileLines, ($featureObject->getLine()+1), 0, $xAddition);
        }

        $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);

        // TODO: Handle edits to background which is just like a scenario - also fix quotes in step name (fix $parameter and $parameterField)

        if ($featureObject->getBackground() instanceof BackgroundNode) {
            //$backgroundLines = $this->processBackground($featureObject->getBackground(), $testFilename, $tempStepsArray, $stepsOpenArray);
        }

        foreach($featureObject->getScenarios() as $scenarioObject) {
            $editedTestFileLines = $this->processScenario($scenarioObject, $testFilename, $featureEndLineNumber);
        }

    }

    private function processBackground($backgroundObject, $testFilename, $tempStepsArray, $stepsOpenArray)
    {



        if (array_key_exists($backgroundObject->getTitle(), $stepsArray)) {
            $tempStepsArray = $stepsArray[$scenarioObject->getTitle()];
        } else {
            $tempStepsArray = array();
        }



        //$editedTestFileLines = $this->fileHandler->openTestFile($testFilename);
        $this->checkForMissingSteps($backgroundObject->getSteps(), $backgroundObject->getTitle(), $tempStepsArray, $testFilename, $stepsOpenArray);

        /*
        $tempArray = [];
        $tempArray[] = $this->writeBeforeEachOpen();

        foreach($backgroundNode->getSteps() as $stepObject) {
            $stepLines = $this->writeStep($testFilename, 'Background', $stepObject->getText(), $stepObject->getArguments());
            $tempArray = array_merge($tempArray, $stepLines);
        }

        $tempArray[] = $this->writeBeforeEachClose();
        */

        return null;
        // return $tempArray;

       //$backgroundLines = 1;

        // $newTestFileLinesArray = array_merge($editedTestFileLines, $tempArray);
        // Change to edit?

        //$this->fileHandler->savePestFile($testFilename, $editedTestFileLines);
    }

    private function processScenario($scenarioObject, $testFilename, $featureEndLineNumber)
    {
        $parsedTestFileArray = $this->pestParser->parseTestFileIntoArrays(file_get_contents($testFilename));

        // The index number is the EndLine of the scenario ('it' in pest language)
        $scenariosArray = $parsedTestFileArray[1];
        $stepsArray = $parsedTestFileArray[2];

        // The index number is the Start Line of the scenario ('it' in pest language)
        $scenariosOpenArray = $parsedTestFileArray[3];
        $stepsOpenArray = $parsedTestFileArray[4];

        if (in_array($scenarioObject->getTitle(), $scenariosArray)) {

            $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

            $this->outputHandler->scenarioIsInTest($scenarioObject->getTitle(), $testFilename);

            $scenarioEndLineNumber = array_search($scenarioObject->getTitle(), $scenariosArray, true);
            $scenarioBeginLineNumber = array_search($scenarioObject->getTitle(), $scenariosOpenArray, true);

            if (array_key_exists($scenarioObject->getTitle(), $stepsArray)) {
                $tempStepsArray = $stepsArray[$scenarioObject->getTitle()];
            } else {
                $tempStepsArray = array();
            }

            if ($scenarioObject instanceof OutlineNode) {

                // Rewrite heading and examples
                $editedTestFileLines[$scenarioBeginLineNumber-1] = $this->pestCreator->writeItOpen($scenarioObject);
                $examples = $this->pestCreator->createOutlineExampleTable($scenarioObject);

                // Deleting the old dataset
                $editedTestFileLines = $this->pestParser->deleteExistingDataset($editedTestFileLines, $scenarioEndLineNumber);
                array_splice($editedTestFileLines, ($scenarioEndLineNumber), 0, $examples);

            }

            $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);

            $this->checkForMissingSteps($scenarioObject->getSteps(), $scenarioObject->getTitle(), $tempStepsArray, $testFilename, $stepsOpenArray);

        } else {

            $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

            $this->outputHandler->scenarioIsNotInTest($scenarioObject->getTitle(), $testFilename);
            $this->errors++;

            $tempAddition = [];
            $tempAddition[] = $this->pestCreator->writeItOpen($scenarioObject);

            foreach ($scenarioObject->getSteps() as $scenarioStepObject) {
                $stepLines = $this->pestCreator->writeStep($testFilename, $scenarioObject->getTitle(), $scenarioStepObject->getText(), $scenarioObject->getArguments());
                $tempAddition = array_merge($tempAddition, $stepLines);
            }

            $tempAddition[] = $this->pestCreator->writeItClose($scenarioObject);

            if ($scenarioObject instanceof OutlineNode) {
                $tempAddition = array_merge($tempAddition, $this->pestCreator->createOutlineExampleTable($scenarioObject));
            }

            array_splice($editedTestFileLines, ($featureEndLineNumber-2), 0, $tempAddition);

            $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);

        }

        return $editedTestFileLines;
    }

    private function checkForMissingSteps($stepsObjectArray, $stepTitle, array $testFileStepsArray, string $testFilename, array $stepsOpenArray)
    {
        // TODO: Refactor this function
        // TODO: CHANGE THIS TO ARRAY OF STEPS INSTEAD OF $SCENARIOOBJECT SO I CAN PASS IN BACKGRROUND STEPS AS WELL

        $tempAddition = [];

        foreach ($stepsObjectArray as $scenarioStepObject) {

            // Check if step exists in the pest test file, if not, create it
            $requiredStepNameArray = $this->pestCreator->calculateRequiredStepName($scenarioStepObject->getText(), $testFilename, $stepTitle);
            $requiredStepname = $requiredStepNameArray[0];
            $parameterString = $requiredStepNameArray[1];
            $parameterFields = $requiredStepNameArray[2];

            if (in_array($requiredStepname, $testFileStepsArray)) {

                if(str_contains($requiredStepname, 'parameter')) {

                    // If contains the phrase "parameter" then rewrite that specific line (including the parameters)
                    $t = array_search($requiredStepname, $testFileStepsArray);

                    $editedTestFileLines1 = $this->fileHandler->openTestFile($testFilename);
                    $editedTestFileLines1[$t-1] = chr(9).chr(9) . $requiredStepname . '('.$parameterString.');'.PHP_EOL;
                    $this->fileHandler->savePestFile($testFilename, $editedTestFileLines1);

                }

                $y = array_search($requiredStepname, $stepsOpenArray);

                $this->outputHandler->stepIsInTest($scenarioStepObject->getText(), $testFilename);

                // But check if has $data and update it accordingly? (Just like datasets and description)
                $stepArguments = $scenarioStepObject->getArguments();
                ray('GH32', $requiredStepname, $stepArguments);
                if(array_key_exists(0, $stepArguments)) { // && $stepArguments[0] instanceof TableNode) {

                    $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

                    $r = $this->pestParser->removeExistingDataFromStep($y, $editedTestFileLines);
                    $stepArgumentsData = $this->pestCreator->convertStepArgumentToString($stepArguments);
                    $data[] = $stepArgumentsData;
                    array_splice($r, ($y+1), 0, $data);

                    $this->fileHandler->savePestFile($testFilename, $r);
                    $data = [];

                }

            } else {

                $editedTestFileLines = $this->fileHandler->openTestFile($testFilename);

                $this->outputHandler->stepIsNotInTest($scenarioStepObject->getText(), $testFilename);
                $this->errors++;

                $result = $this->pestCreator->writeStep($testFilename, $stepTitle, $scenarioStepObject->getText(), $scenarioStepObject->getArguments());

                array_splice($editedTestFileLines, (array_key_last($testFileStepsArray)+1), 0, $result);
                $this->fileHandler->savePestFile($testFilename, $editedTestFileLines);

            }

        }

        return $tempAddition;

    }

}
