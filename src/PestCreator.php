<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Gherkin\Node\TableNode;
use Symfony\Component\Console\Output\OutputInterface;

final class PestCreator
{
    private GherkinParser $gherkinParser;

    private FileHandler $fileHandler;

    public function __construct(private readonly OutputInterface $output)
    {
        $this->gherkinParser = new GherkinParser();
        $this->fileHandler = new FileHandler($output);
    }

    public function createTestFile(string $testFilename, string $featureFileContents): void
    {

        $featureObject = $this->gherkinParser->gherkin($featureFileContents);

        $newTestFileLinesArray = [];
        $newTestFileLinesArray[] = $this->createNewTestFile();
        $newTestFileLinesArray[] = $this->writeDescribeOpen($featureObject->getTitle());
        $description = $this->writeDescribeDescription($featureObject->getDescription());
        $newTestFileLinesArray = array_merge($newTestFileLinesArray, $description);
        $newTestFileLinesArray[] = PHP_EOL;

        foreach($featureObject->getScenarios() as $scenarioObject) {

            $newTestFileLinesArray[] = $this->writeItOpen($scenarioObject);

            foreach ($scenarioObject->getSteps() as $scenarioStepObject) {
                $stepLines = $this->writeStep($testFilename, $scenarioObject->getTitle(), $scenarioStepObject->getText(), $scenarioStepObject->getArguments());
                $newTestFileLinesArray = array_merge($newTestFileLinesArray, $stepLines);
            }

            $newTestFileLinesArray[] = $this->writeItClose($scenarioObject);

            if($scenarioObject instanceof OutlineNode) {
                $newTestFileLinesArray = array_merge($newTestFileLinesArray, $this->createOutlineExampleTable($scenarioObject));
            }

        }

        $newTestFileLinesArray[] = $this->writeDescribeClose();
        $this->fileHandler->savePestFile($testFilename, $newTestFileLinesArray);

    }

    public function createOutlineExampleTable($scenarioObject) : array
    {
        $linesArray = [];

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
            $linesArray[] = chr(9).chr(9). trim($lineText) . "],".PHP_EOL;

        }

        $linesArray[] = chr(9). "]);".PHP_EOL;

        return $linesArray;
    }

    public function convertStepArgumentToString($stepArguments) : string | null
    {
        if(!array_key_exists(0, $stepArguments) || !$stepArguments[0] instanceof TableNode) {
            return null;
        }

        $tableArray = $stepArguments[0]->getTable();
        $headings = reset($tableArray);

        $key = key($tableArray);
        unset($tableArray[$key]);

        $data = [];

        foreach($tableArray as $table) {

            $dataLine = [];
            foreach ($headings as $headingKey => $headingName) {

                $cleanedHeadingName = str_replace(' ', '_', $headingName);
                $dataLine[$cleanedHeadingName] = $table[$headingKey];

            }
            $data[] = $dataLine;
        }

        $lineText = chr(9).chr(9).chr(9).'$data = ['.PHP_EOL;
        foreach($data as $dataLine) {
            $lineText.= chr(9).chr(9).chr(9).chr(9). '[';
            foreach($dataLine as $key => $value) {
                $lineText.= '"' . $key . '" => \'' . $value . '\', ';
            }
            $lineText = substr($lineText, 0, -2);
            $lineText.= "],".PHP_EOL;
        }
        $lineText.= chr(9).chr(9).chr(9)."];".PHP_EOL;

        return $lineText;
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
        $descriptionLines = explode("\n", $describeDescription);

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

    public function writeItOpen($scenarioObject) : string
    {
        if($scenarioObject instanceof ScenarioNode) {
            $itOpenString = chr(9)."it('" . $scenarioObject->getTitle() . "', function () {".PHP_EOL.PHP_EOL;
        }

        if($scenarioObject instanceof OutlineNode) {

            $tableObject = $scenarioObject->getExampleTables();
            $tableArray = $tableObject[0]->getTable();

            $itOpenString = chr(9). "it('".$scenarioObject->getTitle()."', function (";

            foreach(reset($tableArray) as $heading) {

                $cleanedHeading = str_replace(' ', '_', $heading);
                $itOpenString.= "string $" . $cleanedHeading . ", ";

            }

            $itOpenString = substr($itOpenString, 0, -2);

            $itOpenString.= ") {".PHP_EOL;

        }

        return $itOpenString;

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

    public function calculateRequiredStepName(string $stepName, string $testFilename, string $scenarioTitle) : string
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
            $x = $requiredStepName;
        } else {
            $x = str_replace(' ', '_', $stepName);
        }

        $fileHash = hash('crc32', $testFilename);
        $scenarioHash = hash('crc32', $scenarioTitle);
        //$requiredStepname = str_replace('_', ' ', $x);
        $requiredStepname = 'step_' . $fileHash . '_' . $scenarioHash . '_' . $x;

        /*
        $requiredStepName = $this->calculateRequiredStepName($scenarioStepTitle);

        $fileHash = hash('crc32', $testFilename);
        $scenarioHash = hash('crc32', $scenarioTitle);
        $stepName = 'step_' . $fileHash . '_' . $scenarioHash . '_' . $requiredStepName;
        */

        return $requiredStepname;
    }

    public function writeStep(string $testFilename, string $scenarioTitle, string $scenarioStepTitle, $stepArguments) : array
    {
        $parameter = null; // TODO: Check this works
        $parameterField = null; // TODO: Check this works
        $requiredStepName = $this->calculateRequiredStepName($scenarioStepTitle, $testFilename, $scenarioTitle);

        $tempAddition = [];
        $tempAddition[] = chr(9).chr(9).'function ' . $requiredStepName . '('.$parameterField.')'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9).'{'.PHP_EOL;

        $tempAddition[] = $this->convertStepArgumentToString($stepArguments) . PHP_EOL;

        $tempAddition[] = chr(9).chr(9) . chr(9) . '// Insert test for this step here'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9) .'}' . PHP_EOL . PHP_EOL;
        $tempAddition[] = chr(9).chr(9) . $requiredStepName . '('.$parameter.');'. PHP_EOL . PHP_EOL;

        return $tempAddition;
    }

}
