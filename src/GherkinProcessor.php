<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\OutlineNode;
use Symfony\Component\Console\Output\OutputInterface;

final class GherkinProcessor
{
    private GherkinParser $gherkinParser;
    private PestCreator $pestCreator;
    private PestParser $pestParser;

    private OutputHandler $outputHandler;

    private int $errors = 0;
    public function __construct(private readonly OutputInterface $output)
    {
        $this->gherkinParser = new GherkinParser();
        $this->pestCreator = new PestCreator();
        $this->pestParser = new PestParser();
        $this->outputHandler = new OutputHandler($output);
    }

    private function removeExistingDescriptionFromPestFile(array $describeDescription, array $editedTestFileLines) : array
    {
        if (!is_null($describeDescription[0])) {
            $currentLine = $describeDescription[1] - 1;
            $endLine = $describeDescription[2];

            while($currentLine <= $endLine) {
                unset($editedTestFileLines[$currentLine]);
                $currentLine++;
            }

        }

        return $editedTestFileLines;
    }

    private function processScenario($scenarioObject, $editedTestFileLines, $parsedTestFileArray, $testFilename, $featureEndLineNumber)
    {
        // The index number is the EndLine of the scenario ('it' in pest language)
        $scenariosArray = $parsedTestFileArray[1];
        $stepsArray = $parsedTestFileArray[2];

        // The index number is the Start Line of the scenario ('it' in pest language)
        $scenariosOpenArray = $parsedTestFileArray[3];

        if (in_array($scenarioObject->getTitle(), $scenariosArray)) {

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
                $editedTestFileLines[$scenarioBeginLineNumber-2] = $this->pestCreator->writeItOpen($scenarioObject);
                $examples = $this->pestCreator->writeOutlineExampleTable($scenarioObject);

                // Deleting the old dataset - MOVE TO A PEST CLASS
                $editedTestFileLines = $this->pestCreator->deleteExistingDataset($editedTestFileLines, $scenarioEndLineNumber);

                array_splice($editedTestFileLines, ($scenarioEndLineNumber-1), 0, $examples);

            }

            $tempAddition = $this->checkForMissingSteps($scenarioObject, $tempStepsArray, $testFilename);
            array_splice($editedTestFileLines, ($scenarioEndLineNumber-1), 0, $tempAddition);

        } else {

            $this->outputHandler->scenarioIsNotInTest($scenarioObject->getTitle(), $testFilename);
            $this->errors++;

            $tempAddition = [];
            $tempAddition[] = $this->pestCreator->writeItOpen($scenarioObject);

            foreach ($scenarioObject->getSteps() as $scenarioStepObject) {
                $stepLines = $this->pestCreator->writeStep($testFilename, $scenarioObject->getTitle(), $scenarioStepObject->getText());
                $tempAddition = array_merge($tempAddition, $stepLines);
            }

            $tempAddition[] = $this->pestCreator->writeItClose($scenarioObject);

            if ($scenarioObject instanceof OutlineNode) {
                $tempAddition = array_merge($tempAddition, $this->pestCreator->writeOutlineExampleTable($scenarioObject));
            }

            array_splice($editedTestFileLines, ($featureEndLineNumber-2), 0, $tempAddition);

        }

        return $editedTestFileLines;
    }

    public function processFeatureScenarios(string $featureFileContents, string $testFilename, array $parsedTestFileArray): void
    {
        $editedTestFileLines = file($testFilename);
        $featureObject = $this->gherkinParser->gherkin($featureFileContents);
        $featuresArray = $parsedTestFileArray[0];
        $featureEndLineNumber = array_search($featureObject->getTitle(), $featuresArray, true);

        $testFileContents = @file_get_contents($testFilename, true);
        $describeDescription = $this->pestParser->getDescribeDescription($testFileContents);

        $editedTestFileLines = $this->removeExistingDescriptionFromPestFile($describeDescription, $editedTestFileLines);

        // Write feature description (including rules) from feature file, if it exists
        if(!is_null($featureObject->getDescription())) {
            $xAddition = $this->pestCreator->writeDescribeDescription($featureObject->getDescription());
            array_splice($editedTestFileLines, ($featureObject->getLine()+1), 0, $xAddition);
        }

        foreach($featureObject->getScenarios() as $scenarioObject) {
            $editedTestFileLines = $this->processScenario($scenarioObject, $editedTestFileLines, $parsedTestFileArray, $testFilename, $featureEndLineNumber);
        }

        $allContent = implode("", $editedTestFileLines);
        file_put_contents($testFilename, $allContent);

    }

    private function checkForMissingSteps($scenarioObject, array $testFileStepsArray, string $testFilename)
    {
        $tempAddition = [];

        foreach ($scenarioObject->getSteps() as $scenarioStepObject) {

            // Check if step exists in the pest test file, if not, create it
            $requiredStepname = $this->pestCreator->calculateRequiredStepName($scenarioStepObject->getText());
            $fileHash = hash('crc32', $testFilename);
            $scenarioHash = hash('crc32', $scenarioObject->getTitle());
            $requiredStepname = str_replace('_', ' ', $requiredStepname);
            $requiredStepname = $fileHash . ' ' . $scenarioHash . ' ' . $requiredStepname;

            if (in_array($requiredStepname, $testFileStepsArray)) {

                $this->outputHandler->stepIsInTest($scenarioStepObject->getText(), $testFilename);

            } else {
                $this->outputHandler->stepIsNotInTest($scenarioStepObject->getText(), $testFilename);
                $this->errors++;

                $result = $this->pestCreator->writeStep($testFilename, $scenarioObject->getTitle(), $scenarioStepObject->getText());
                $tempAddition = array_merge($tempAddition, $result);

            }

        }

        return $tempAddition;

    }
}
