<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\TableNode;
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

    private function removeExistingDataFromStep(int $startLine, array $editedTestFileLines) : array
    {
        $currentLine = $startLine+1;
        //$endLine = $describeDescription[2];
        $endLine = 100; // TODO - set to line number at end of $editedTestFileLines array

        while($currentLine <= $endLine) {

            if (trim($editedTestFileLines[$currentLine]) == '];') {
                $endLine = $currentLine;
            }
            unset($editedTestFileLines[$currentLine]);
            $currentLine++;
        }

        return $editedTestFileLines;
    }

    private function processScenario($scenarioObject, $testFilename, $featureEndLineNumber)
    {
        $parsedTestFileArray = $this->pestParser->parseTestFile(file_get_contents($testFilename));

        // The index number is the EndLine of the scenario ('it' in pest language)
        $scenariosArray = $parsedTestFileArray[1];
        $stepsArray = $parsedTestFileArray[2];

        // The index number is the Start Line of the scenario ('it' in pest language)
        $scenariosOpenArray = $parsedTestFileArray[3];
        $stepsOpenArray = $parsedTestFileArray[4];

        if (in_array($scenarioObject->getTitle(), $scenariosArray)) {

            $editedTestFileLines = file($testFilename);

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
                $examples = $this->pestCreator->writeOutlineExampleTable($scenarioObject);

                // Deleting the old dataset
                $editedTestFileLines = $this->pestCreator->deleteExistingDataset($editedTestFileLines, $scenarioEndLineNumber);
                array_splice($editedTestFileLines, ($scenarioEndLineNumber), 0, $examples);

            }

            $allContent = implode("", $editedTestFileLines);
            file_put_contents($testFilename, $allContent);

            $this->checkForMissingSteps($scenarioObject, $tempStepsArray, $testFilename, $stepsOpenArray);

        } else {

            $editedTestFileLines = file($testFilename);

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

            $allContent = implode("", $editedTestFileLines);
            file_put_contents($testFilename, $allContent);

        }

        return $editedTestFileLines;
    }

    public function processFeatureScenarios(string $featureFileContents, string $testFilename): void
    {
        $parsedTestFileArray = $this->pestParser->parseTestFile(file_get_contents($testFilename));

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

        $allContent = implode("", $editedTestFileLines);
        file_put_contents($testFilename, $allContent);

        foreach($featureObject->getScenarios() as $scenarioObject) {
            $editedTestFileLines = $this->processScenario($scenarioObject, $testFilename, $featureEndLineNumber);
        }

    }

    private function checkForMissingSteps($scenarioObject, array $testFileStepsArray, string $testFilename, array $stepsOpenArray)
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

                $y = array_search($requiredStepname, $stepsOpenArray);
                $editedTestFileLines = file($testFilename);

                $this->outputHandler->stepIsInTest($scenarioStepObject->getText(), $testFilename);

                // But check if has $data and update it accordingly? (Just like datasets and description)
                $stepArguments = $scenarioStepObject->getArguments();
                if($stepArguments[0] instanceof TableNode) {

                    $r = $this->removeExistingDataFromStep($y, $editedTestFileLines);
                    $stepArgumentsData = $this->pestCreator->writeStepArgumentTable($stepArguments);
                    $data[] = $stepArgumentsData;
                    array_splice($r, ($y+1), 0, $data);
                    $allContent2 = implode("", $r);
                    file_put_contents($testFilename, $allContent2);

                }


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
