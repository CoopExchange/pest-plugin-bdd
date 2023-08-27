<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\ScenarioNode;

final class PestCreator
{
    private GherkinParser $gherkinParser;

    public function __construct()
    {
        $this->gherkinParser = new GherkinParser();
    }

    public function createTestFile(string $testFilename, string $featureFileContents): void
    {

        $featureObject = $this->gherkinParser->gherkin($featureFileContents);

        $tempAddition = [];
        $tempAddition[] = $this->createNewTestFile();
        $tempAddition[] = $this->writeDescribeOpen($featureObject->getTitle());
        $description = $this->writeDescribeDescription($featureObject->getDescription());
        $tempAddition = array_merge($tempAddition, $description);
        $tempAddition[] = PHP_EOL;

        foreach($featureObject->getScenarios() as $scenarioObject) {

            $tempAddition[] = $this->writeItOpen($scenarioObject);

            foreach ($scenarioObject->getSteps() as $scenarioStepObject) {
                $stepLines = $this->writeStep($testFilename, $scenarioObject->getTitle(), $scenarioStepObject->getText());
                $tempAddition = array_merge($tempAddition, $stepLines);
            }

            $tempAddition[] = $this->writeItClose($scenarioObject);

            if($scenarioObject instanceof OutlineNode) {
                $tempAddition = array_merge($tempAddition, $this->writeOutlineExampleTable($scenarioObject));
            }

        }

        $tempAddition[] = $this->writeDescribeClose();
        $this->closeNewTestFile($testFilename, $tempAddition);

    }

    public function writeOutlineExampleTable($scenarioObject) : array
    {
        $tempAddition = [];

        $tableObject = $scenarioObject->getExampleTables();
        $tableArray = $tableObject[0]->getTable();

        $key = key($tableArray);
        unset($tableArray[$key]);

        foreach($tableArray as $table) {

            $lineText = "[";
            foreach($table as $line) {
                $lineText.= "'" . trim($line) . "', ";
            }

            $lineText = substr($lineText, 0, -2);
            $tempAddition[] = chr(9).chr(9). trim($lineText) . "],".PHP_EOL;

        }

        $tempAddition[] = chr(9). "]);".PHP_EOL;

        return $tempAddition;
    }



    private function createNewTestFile()
    {
        return "<?php".PHP_EOL;
    }

    private function writeDescribeOpen($describeTitle): string
    {
       return "describe('" . $describeTitle . "', function () {".PHP_EOL;
    }


    public function writeDescribeDescription($describeDescription): array
    {
        // TODO - change to return values so I can reuse this function

        $descriptionLines = explode("\n", $describeDescription);
        //ray($descriptionLines);

        $x[] = PHP_EOL . chr(9). "/*" . PHP_EOL ;

        foreach($descriptionLines as $descriptionLine) {
            $x[] = chr(9). " *" . $descriptionLine . PHP_EOL ;
        }

        $x[] = chr(9). " */" . PHP_EOL;

        return $x;
    }

    private function writeDescribeClose(): string
    {
        return "});".PHP_EOL.PHP_EOL;
    }

    private function closeNewTestFile(string $testFilename, array $testFileContentsArray): void
    {
        $allContent = implode("", $testFileContentsArray);
        file_put_contents($testFilename, $allContent);
    }

    public function writeItOpen($scenarioObject) : string
    {
        if($scenarioObject instanceof ScenarioNode) {
            return chr(9)."it('" . $scenarioObject->getTitle() . "', function () {".PHP_EOL.PHP_EOL;
        }

        if($scenarioObject instanceof OutlineNode) {

            $tableObject = $scenarioObject->getExampleTables();
            $tableArray = $tableObject[0]->getTable();

            $variables = chr(9). "it('".$scenarioObject->getTitle()."', function (";

            foreach(reset($tableArray) as $heading) {

                $cleanedHeading = str_replace(' ', '_', $heading);
                $variables.= "string $" . $cleanedHeading . ", ";

            }

            $variables = substr($variables, 0, -2);

            $variables.= ") {".PHP_EOL;

            return $variables;

        }

    }

    public function writeItClose($scenarioObject) : string
    {
        if($scenarioObject instanceof ScenarioNode) {
            return chr(9)."})->todo();".PHP_EOL.PHP_EOL;
        }

        if($scenarioObject instanceof OutlineNode) {
            return chr(9).  "})->todo()->with([".PHP_EOL;
        }

    }

    public function deleteExistingDataset(array $editedTestFileLines, int $scenarioEndLineNumber) : array
    {
        foreach($editedTestFileLines as $key => $editedTestFileLine) {

            if($key >= ($scenarioEndLineNumber-1) && trim($editedTestFileLine) == "]);") {

                $currentLine = ($scenarioEndLineNumber-1);
                while($currentLine <= $key) {
                    unset($editedTestFileLines[$currentLine]);
                    $currentLine++;
                }
                break;
            }

        }

        return $editedTestFileLines;
    }

    public function calculateRequiredStepName(string $stepName) : string
    {
        // Strip out bad characters that will make the function break - for now, only commas
        $stepName = str_replace(',', '', $stepName);

        $re = "/(?<=['\"\\(])[^\"()\\n']*?(?=[\\)\"'])/m";
        preg_match_all($re, $stepName, $matches);

        $parameter = null;
        $parameterField = null;
        if (array_key_exists(0, $matches) && array_key_exists(0, $matches[0])) {
            $parameter = '"'.$matches[0][0].'"';
            $requiredStepName = str_replace(' ' . $parameter . '', '', $stepName);
            $requiredStepName = str_replace(' ', '_', $requiredStepName);
            $parameterField = '$parameter'; // TODO: fix parameters
            return $requiredStepName;
        } else {
            return str_replace(' ', '_', $stepName);
        }
    }

    public function writeStep(string $testFilename, string $scenarioTitle, string $scenarioStepTitle) : array
    {
        $parameter = null;
        $parameterField = null;
        $requiredStepName = $this->calculateRequiredStepName($scenarioStepTitle);

        $fileHash = hash('crc32', $testFilename);
        $scenarioHash = hash('crc32', $scenarioTitle);

        $tempAddition = [];
        $tempAddition[] = chr(9).chr(9).'function step_' . $fileHash . '_' . $scenarioHash . '_' . $requiredStepName . '('.$parameterField.')'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9).'{'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9).chr(9).'// Insert test for this step here'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9).'}'.PHP_EOL.PHP_EOL;
        $tempAddition[] = chr(9).chr(9).'step_' . $fileHash . '_' . $scenarioHash . '_' . $requiredStepName . '('.$parameter.');'.PHP_EOL.PHP_EOL;

        return $tempAddition;
    }

}
