<?php

namespace Vmeretail\PestPluginBdd;

use Behat\Gherkin\Node\BackgroundNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\PyStringNode;
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

    public function processBackground(BackgroundNode $backgroundNode, string $testFilename) : array
    {
        $tempArray = [];
        $tempArray[] = $this->writeBeforeEachOpen();

        foreach($backgroundNode->getSteps() as $stepObject) {
            $stepLines = $this->writeStep($testFilename, 'Background', $stepObject->getText(), $stepObject->getArguments());
            $tempArray = array_merge($tempArray, $stepLines);
        }

        $tempArray[] = $this->writeBeforeEachClose();

        return $tempArray;
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

        if ($featureObject->getBackground() instanceof BackgroundNode) {
            $backgroundLines = $this->processBackground($featureObject->getBackground(), $testFilename);
            $newTestFileLinesArray = array_merge($newTestFileLinesArray, $backgroundLines);
        }

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
        if(!array_key_exists(0, $stepArguments) ) { // || !$stepArguments[0] instanceof TableNode) {
            return null;
        }

        $stepArgument = $stepArguments[0];

        if ($stepArgument instanceof PyStringNode) {
            ray('CV11', $stepArgument);

            $lineText = $this->createStepVariableString($stepArgument->getStrings());

        }

        if($stepArgument instanceof TableNode) {

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

            $lineText = $this->createStepVariableString($data);

        }


        return $lineText;
    }

    private function createStepVariableString($data)
    {
        $lineText = chr(9).chr(9).chr(9).'$data = ['.PHP_EOL;
        foreach($data as $dataLine) {

            $lineText.= chr(9).chr(9).chr(9).chr(9). '[';

            // If its an array or if its a string
            if(is_array($dataLine)) {
                foreach($dataLine as $key => $value) {
                    $lineText.= '"' . $key . '" => \'' . $value . '\', ';
                }
            } else {
                $lineText.= '"' . $dataLine . '"';
            }

            //$lineText = substr($lineText, 0, -2);
            $lineText.= "],".PHP_EOL;

        }
        $lineText.= chr(9).chr(9).chr(9)."];".PHP_EOL;

        ray('BED2', $lineText);

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

    public function writeBeforeEachOpen() : string
    {

        $beforeEachOpenString = chr(9)."beforeEach(function () {".PHP_EOL.PHP_EOL;
        return $beforeEachOpenString;

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

    public function writeBeforeEachClose() : string
    {
        return chr(9)."});".PHP_EOL.PHP_EOL;
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

    public function calculateRequiredStepName(string $stepName, string $testFilename, string $scenarioTitle)
    {
        // Strip out bad characters that will make the function break - for now, only commas and :
        $stepName = str_replace(',', '', $stepName);
        $stepName = str_replace(':', '', $stepName);
        $stepName = str_replace('-', '_', $stepName);

        $parameterString = null;
        $parameterFields = null;

        //ray('INPUT STEP NAME', $stepName);

        $re = "/(?<=['\"\\(])[^\"()\\n']*?(?=[\\)\"'])/m";
        preg_match_all($re, $stepName, $matches);

        if (array_key_exists(0, $matches) && array_key_exists(0, $matches[0])) {

            foreach($matches[0] as $key => $match) {

                // Ignore odd items in the array (as they are the middle between vars)
                if($key&1) {
                    unset($matches[0][$key]);
                    continue;
                }

            }

            $parameters = array_values($matches[0]);

            // Now convert the stepName
            foreach($parameters as $key => $match) {

                $stepName = str_replace($match, 'parameter' . $key, $stepName);
                $parameterFields.= '$parameter'.$key.', ';
                $parameterString.= '"'.$match.'", ';

            }

            $stepName = str_replace('"', '', $stepName);

            ray('XCV2', $stepName, $parameters);

            $parameterFields = substr($parameterFields, 0, -2);
            $parameterString = substr($parameterString, 0, -2);

        } else {
            $stepName = str_replace(' ', '_', $stepName);
        }

        $fileHash = hash('crc32', $testFilename);
        $scenarioHash = hash('crc32', $scenarioTitle);

        $requiredStepName = str_replace(' ', '_', $stepName);
        $requiredStepname = 'step_' . $fileHash . '_' . $scenarioHash . '_' . $requiredStepName;

        return [$requiredStepname, $parameterString, $parameterFields];
    }

    public function writeStep(string $testFilename, string $scenarioTitle, string $scenarioStepTitle, $stepArguments) : array
    {
        $requiredStepNameArray = $this->calculateRequiredStepName($scenarioStepTitle, $testFilename, $scenarioTitle);
        $requiredStepName = $requiredStepNameArray[0];
        $parameterString = $requiredStepNameArray[1];
        $parameterFields = $requiredStepNameArray[2];

        $tempAddition = [];
        $tempAddition[] = chr(9).chr(9).'function ' . $requiredStepName . '('.$parameterFields.')'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9).'{'.PHP_EOL;

        $tempAddition[] = $this->convertStepArgumentToString($stepArguments) . PHP_EOL;

        $tempAddition[] = chr(9).chr(9) . chr(9) . '// Insert test for this step here'.PHP_EOL;
        $tempAddition[] = chr(9).chr(9) .'}' . PHP_EOL . PHP_EOL;
        $tempAddition[] = chr(9).chr(9) . $requiredStepName . '('.$parameterString.');'. PHP_EOL . PHP_EOL;

        return $tempAddition;
    }

}
