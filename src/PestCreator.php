<?php

declare(strict_types=1);

namespace CoopExchange\PestPluginBdd;

use Behat\Gherkin\Node\BackgroundNode;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\ScenarioInterface;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Gherkin\Node\StepNode;
use Behat\Gherkin\Node\TableNode;
use Illuminate\Support\Collection;
use Symfony\Component\Console\Output\OutputInterface;

final class PestCreator
{
    private GherkinParser $gherkinParser;

    private FileHandler $fileHandler;

    public function __construct(OutputInterface $output)
    {
        $this->gherkinParser = new GherkinParser();
        $this->fileHandler = new FileHandler($output);
    }

    private function createBackground(BackgroundNode $backgroundNode): Collection
    {

        // COuld move this to a construct if sub divide this into classes
        $tempArray = new Collection();
        $tempArray->push($this->writeBeforeEachOpen());

        $tempArray->push($this->createTestFromSteps($backgroundNode->getSteps()));

        $tempArray->push($this->writeBeforeEachClose());

        return $tempArray;
    }

    private function createScenario(ScenarioInterface $scenarioObject): Collection
    {
        $newTestFileLines = new Collection();
        $newTestFileLines->push($this->writeDescribeOpen((string)$scenarioObject->getTitle(), true));

        $newTestFileLines->push($this->createTestFromSteps($scenarioObject->getSteps()));

        $newTestFileLines->push($this->writeDescribeClose(true));

//        if ($scenarioObject instanceof OutlineNode) {
//            $newTestFileLines->push($this->createOutlineExampleTable($scenarioObject));
//        }

        return $newTestFileLines;
    }

    /**
     * @param StepNode[] $steps
     * @return Collection
     */
    public function createTestFromSteps(array $steps): Collection
    {
        $newTestFileLines = new Collection();

        foreach ($steps as $step) {
            $newTestFileLines->push(
                $this->writeTestOpen($step)
            );

            if (count($step->getArguments()) > 0) {
                $newTestFileLines->push($this->convertStepArgumentToString($step->getArguments()) . Helpers::newLine());
            }

            $newTestFileLines->push(Helpers::indent() . Helpers::indent() . Helpers::indent() . '// Insert test for this step here' . Helpers::newLine());

            $newTestFileLines->push($this->writeTestClose($step));
        }

        return $newTestFileLines;
    }

    public function createTestFile(string $testFilename, string $featureFileContents): void
    {
        $featureObject = $this->gherkinParser->gherkin($featureFileContents);

        $newTestFile = $this->createFeatureTests($featureObject);

        $newTestFile->prepend($this->createNewTestFile());

        $this->fileHandler->savePestFile($testFilename, $newTestFile);

    }

    public function createFeatureTests(FeatureNode $featureObject): Collection
    {
        $newTestFile = new Collection();

        $newTestFile->push($this->writeDescribeOpen((string)$featureObject->getTitle()));

        if (!is_null($featureObject->getDescription())) {
            $newTestFile->push($this->writeDescribeDescription($featureObject->getDescription()));
        }

        if ($featureObject->getBackground() instanceof BackgroundNode) {

            $newTestFile->push(
                $this->createBackground($featureObject->getBackground())
            );

        }

        foreach ($featureObject->getScenarios() as $scenarioObject) {
            $newTestFile->push(
                $this->createScenario($scenarioObject)
            );
        }

        $newTestFile->push($this->writeDescribeClose());

        return $newTestFile;
    }

//    public function createOutlineExampleTable($scenarioObject): Collection
//    {
//        $lines = new Collection();
//
//        $tableObject = $scenarioObject->getExampleTables();
//        $tableArray = $tableObject[0]->getTable();
//
//        $key = key($tableArray);
//        unset($tableArray[$key]);
//
//        foreach ($tableArray as $table) {
//
//            $lineText = "[";
//            foreach ($table as $line) {
//                $lineText .= "'" . trim($line) . "', ";
//            }
//
//            $lineText = substr($lineText, 0, -2);
//
//            $lines->push(
//                Helpers::indent() .
//                Helpers::indent() .
//                trim($lineText) .
//                "]," .
//                Helpers::newLine()
//            );
//
//        }
//
//        $lines->push(
//            Helpers::indent() .
//            "]);" .
//            Helpers::newLine()
//        );
//
//        return $lines;
//    }

    public function convertStepArgumentToString($stepArguments): string|null
    {
        if (!array_key_exists(0, $stepArguments)) { // || !$stepArguments[0] instanceof TableNode) {
            return null;
        }

        $stepArgument = $stepArguments[0];

        if ($stepArgument instanceof PyStringNode) {

            $lineText = $this->createStepVariableString($stepArgument->getStrings());

        }

        if ($stepArgument instanceof TableNode) {

            $tableArray = $stepArguments[0]->getTable();
            $headings = reset($tableArray);

            $key = key($tableArray);
            unset($tableArray[$key]);

            $data = [];

            foreach ($tableArray as $table) {

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

    private function createStepVariableString($data): string
    {
        $lineText = Helpers::indent() . Helpers::indent() . Helpers::indent() . '$data = new \Illuminate\Support\Collection([' . Helpers::newLine();
        foreach ($data as $dataLine) {

            $lineText .= Helpers::indent() . Helpers::indent() . Helpers::indent() . Helpers::indent() . '[';

            // If its an array or if its a string
            if (is_array($dataLine)) {
                foreach ($dataLine as $key => $value) {
                    $lineText .= '"' . $key . '" => \'' . $value . '\', ';
                }
            } else {
                $lineText .= '"' . $dataLine . '"';
            }

            //$lineText = substr($lineText, 0, -2);
            $lineText .= "]," . Helpers::newLine();

        }
        $lineText .= Helpers::indent() . Helpers::indent() . Helpers::indent() . "]);" . Helpers::newLine();

        return $lineText;
    }


    private function createNewTestFile(): string
    {
        return "<?php" . Helpers::newLine();
    }

    private function writeDescribeOpen(string $describeTitle, bool $indent = false): string
    {
        $string = '';

        if ($indent) {
            $string .= Helpers::indent();
        }

        return $string . "describe('" . $describeTitle . "', function () {" . Helpers::newLine();
    }


    public function writeDescribeDescription(string $describeDescription): Collection
    {
        $descriptionLines = explode("\n", $describeDescription);

        $tempLines = new Collection();

        $tempLines->push(Helpers::indent() . "/*" . Helpers::newLine());

        foreach ($descriptionLines as $descriptionLine) {
            $tempLines->push(Helpers::indent() . " *" . $descriptionLine . Helpers::newLine());
        }

        $tempLines->push(Helpers::indent() . " */" . Helpers::newLine() . Helpers::newLine());

        return $tempLines;
    }

    private function writeDescribeClose(bool $indent = false): string
    {
        $string = '';

        if ($indent) {
            $string .= Helpers::indent();
        }

        $string .= "});" . Helpers::newLine() . Helpers::newLine();

        return $string;
    }

    public function writeBeforeEachOpen(): string
    {
        return Helpers::newLine() . Helpers::indent() . "beforeEach(function () {" . Helpers::newLine();
    }

    public function writeBeforeEachClose(): string
    {
        return Helpers::indent() . "});" . Helpers::newLine() . Helpers::newLine();
    }

    public function writeTestOpen(StepNode $scenarioObject): string
    {
        return Helpers::newLine() . Helpers::indent() . Helpers::indent() . "test('" . $scenarioObject->getKeyword() . " " . $scenarioObject->getText() . "', function () {" . Helpers::newLine();
    }


    public function writeTestClose(ScenarioNode|StepNode|OutlineNode|BackgroundNode $scenarioObject): string
    {
        if ($scenarioObject instanceof ScenarioNode) {
            return Helpers::indent() . "});" . Helpers::newLine();
        }

        if ($scenarioObject instanceof StepNode) {
            return Helpers::indent() . Helpers::indent() . "})->todo();" . Helpers::newLine();
        }

        if ($scenarioObject instanceof OutlineNode) {
            return Helpers::indent() . "})->todo()->with([" . Helpers::newLine();
        }

        //Must be a BackgroundNode
        return Helpers::indent() . "});" . Helpers::newLine();

    }

    public function addUpdatedFeatureToTestFile(string $featureFileContents, string $testFileName): void
    {
        $featureObject = $this->gherkinParser->gherkin($featureFileContents);
        $featureTests = $this->createFeatureTests($featureObject);

        $testFileLines = $this->fileHandler->openTestFile($testFileName);
        $newTestFile = $testFileLines->merge($featureTests);

        $this->fileHandler->savePestFile($testFileName, $newTestFile);
    }

}
