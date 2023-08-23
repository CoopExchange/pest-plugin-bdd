<?php

declare(strict_types=1);

namespace Vmeretail\PestPluginBdd;

// use Pest\Contracts\Plugins\AddsOutput;
use Behat\Gherkin\Keywords\ArrayKeywords;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\ScenarioNode;
use Behat\Gherkin\Parser;
use Pest\Contracts\Plugins\HandlesArguments;
use Pest\Plugins\Concerns\HandleArguments;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\InputDefinition;
use Pest\Support\Str;

/**
 * @internal
 */
final class Plugin implements HandlesArguments
{
    use HandleArguments;

    const FEATURE = 'describe';
    const SCENARIO = 'it';
    const STEP = 'step_';

    private const BDD_OPTION = 'bdd';

    private const BDD_PATH = 'tests/Feature/';

    private int $errors = 0;

    private bool $createTests = false;

    private $featuresArray = [];
    private $scenariosArray = [];
    private $stepsArray = [];

    public function __construct(private OutputInterface $output)
    {

    }

    public function handleArguments(array $arguments): array
    {

        if (! $this->hasArgument('--bdd', $arguments)) {
            return $arguments;
        }

        if ($this->hasArgument('--create-tests', $arguments)) {
            $this->createTests = true;
        }

        $files = $this->findFiles();

        $this->checkTestsHaveFeatureFiles($files);

        $this->checkFeaturesHaveTestFiles($files);

        $this->output->writeln('');
        $this->output->writeln('<error>There are ' . $this->errors . ' errors to be fixed</error>');

        exit(0);
    }

    private function gherkin($filename) : FeatureNode
    {
        $keywords = new ArrayKeywords(array(
            'en' => array(
                'feature'          => 'Feature',
                'background'       => 'Background',
                'scenario'         => 'Scenario',
                'scenario_outline' => 'Scenario Outline|Scenario Template',
                'examples'         => 'Examples|Scenarios',
                'given'            => 'Given',
                'when'             => 'When',
                'then'             => 'Then',
                'and'              => 'And',
                'but'              => 'But'
            )
        ));
        $lexer  = new Lexer($keywords);
        $parser = new Parser($lexer);

        return $parser->parse($filename);
    }

    private function checkTestsHaveFeatureFiles($files)
    {
        $testFilesArray = $this->getFilteredListofFiles($files, '.php');

        $this->output->writeln('<info>Processing ' . count($testFilesArray) . ' test files to ensure they have corresponding feature files</info>');

        foreach($testFilesArray as $testFile) {

            $featureFilename = str_replace('.php', '.feature', $testFile);

            $featureFile = @file_get_contents($featureFilename, true);

            if($featureFile === FALSE)
            {
                $this->output->writeln('<bg=red;options=bold> FEATURE </> ' . $featureFilename . ' does NOT exist');
                $this->errors++;
            } else {
                $this->output->writeln('<bg=green;options=bold> FEATURE </> ' . $featureFilename . ' does exist');
            }

        }

        $this->output->writeln('');

    }

    private function findFiles() : array
    {
        $directory = new \RecursiveDirectoryIterator(self::BDD_PATH);
        $iterator = new \RecursiveIteratorIterator($directory);
        $files = array();
        foreach ($iterator as $info) {
            $files[] = $info->getPathname();
        }

        return $files;
    }

    private function checkForMissingSteps(ScenarioNode $scenarioObject, array $testFileStepsArray, $testFilename)
    {
        foreach ($scenarioObject->getSteps() as $scenarioStepObject) {

            $re = "/(?<=['\"\\(])[^\"()\\n']*?(?=[\\)\"'])/m";
            preg_match_all($re, $scenarioStepObject->getText(), $matches);

            $parameter = null;
            $requiredStepName = $scenarioStepObject->getText();
            if (array_key_exists(0, $matches) && array_key_exists(0, $matches[0])) {
                $parameter = '"'.$matches[0][0].'"';
                $requiredStepName = str_replace(' ' . $parameter . '', '', $scenarioStepObject->getText());
            }

            if (in_array($requiredStepName, $testFileStepsArray)) {
                $this->output->writeln('<bg=green;options=bold> STEP </> <bg=gray>' . $scenarioStepObject->getText() . '</> IS in the '.$testFilename.' test file');
            } else {
                $this->output->writeln('<bg=red;options=bold> STEP </> <bg=gray>' . $scenarioStepObject->getText() . '</> is NOT in the '.$testFilename.' test file');
                $this->errors++;
            }

        }
    }

    private function getFilteredListofFiles(array $fileList, string $filter) : array
    {
        $filesArray = array_filter($fileList, function ($var) use($filter)
        {
            return (stripos($var, $filter) !== false);
        });

        // need to reset array
        return array_values($filesArray);
    }

    private function createTestFile(string $testFilename, string $featureFileContents)
    {
        if($this->createTests !== true) {
            return;
        }

        $appendVar = fopen($testFilename,'a');

        // writing new lines to the file
        $wit = fwrite($appendVar,"<?php".PHP_EOL);
        $wit = fwrite($appendVar, PHP_EOL);

        $featureObject = $this->gherkin($featureFileContents);

        /*
         * describe('this is a feature', function () {
         */

        $wit = fwrite($appendVar,"describe('" . $featureObject->getTitle() . "', function () {".PHP_EOL);
        $wit = fwrite($appendVar,PHP_EOL);


        foreach($featureObject->getScenarios() as $scenarioObject) {

            // Check all scenarios in the feature file have a corresponding 'it' in the test file
            //$this->output->writeln('Scenario: ' . $scenarioObject->getTitle());

            if (in_array($scenarioObject->getTitle(), $this->scenariosArray)) {

            } else {
                $this->output->writeln('<bg=red;options=bold> SCENARIO </> "' . $scenarioObject->getTitle() . '" is NOT in the '.$testFilename.' test file');
                $this->errors++;

                $wit = fwrite($appendVar,chr(9)."it('" . $scenarioObject->getTitle() . "', function () {".PHP_EOL);
                $wit = fwrite($appendVar,PHP_EOL);

                foreach ($scenarioObject->getSteps() as $scenarioStepObject) {

                    $re = "/(?<=['\"\\(])[^\"()\\n']*?(?=[\\)\"'])/m";
                    preg_match_all($re, $scenarioStepObject->getText(), $matches);

                    $parameter = null;
                    $parameterField = null;
                    if (array_key_exists(0, $matches) && array_key_exists(0, $matches[0])) {
                        $parameter = '"'.$matches[0][0].'"';
                        $requiredStepName = str_replace(' ' . $parameter . '', '', $scenarioStepObject->getText());
                        $requiredStepName = str_replace(' ', '_', $requiredStepName);
                        $parameterField = '$parameter';

                    } else {
                        $requiredStepName = str_replace(' ', '_', $scenarioStepObject->getText());
                    }

                    //$wit = fwrite($appendVar,PHP_EOL);
                    $wit = fwrite($appendVar, chr(9).chr(9).'function step_' . $requiredStepName . '('.$parameterField.')'.PHP_EOL);
                    $wit = fwrite($appendVar,chr(9).chr(9).'{'.PHP_EOL);
                    $wit = fwrite($appendVar,chr(9).chr(9).chr(9).'// Insert test for this step here'.PHP_EOL);
                    $wit = fwrite($appendVar,chr(9).chr(9).'}'.PHP_EOL);
                    $wit = fwrite($appendVar,PHP_EOL);
                    $wit = fwrite($appendVar, chr(9).chr(9).'step_' . $requiredStepName . '('.$parameter.');'.PHP_EOL);
                    $wit = fwrite($appendVar,PHP_EOL);

                }

                $wit = fwrite($appendVar,chr(9)."})->todo();".PHP_EOL);
                $wit = fwrite($appendVar,PHP_EOL);

            }

        }

        $wit = fwrite($appendVar,"});".PHP_EOL);
        $wit = fwrite($appendVar,PHP_EOL);

        // Closing the file
        fclose($appendVar);

    }

    private function checkFeaturesHaveTestFiles($files)
    {
        $featureFilesArray = $this->getFilteredListofFiles($files, '.feature');

        $this->output->writeln('<info>Processing ' . count($featureFilesArray) . ' feature files to ensure they have corresponding test files</info>');

        foreach($featureFilesArray as $featureFileName) {

            $testFilename = str_replace('.feature', '.php', $featureFileName);

            $featureFileContents = @file_get_contents($featureFileName, true);
            $testFile = @file_get_contents($testFilename, true);

            if($testFile === FALSE)
            {
                $this->output->writeln('<bg=red;options=bold> TEST </> ' . $testFilename . ' does not exist for Feature: ' . $this->featureName($featureFileContents));
                $this->errors++;

                $this->processFeatureScenarios($featureFileContents, '');

                $this->createTestFile($testFilename, $featureFileContents);


            } else {

                $this->output->writeln('<bg=green;options=bold> TEST </> ' . $testFilename . ' exists for Feature: ' . $this->featureName($featureFileContents));
                // Check test file contains everything in the feature file

                $this->parseTestFile($testFile);

                $this->processFeatureScenarios($featureFileContents, $testFilename);

            }

            $this->output->writeln('');

        }


    }

    private function featureName(string $featureFileContents)
    {
        $featureObject = $this->gherkin($featureFileContents);
        return $featureObject->getTitle();
    }

    private function processFeatureScenarios(string $featureFileContents, string $testFilename)
    {
        $featureObject = $this->gherkin($featureFileContents);

        foreach($featureObject->getScenarios() as $scenarioObject) {

            // Check all scenarios in the feature file have a corresponding 'it' in the test file
            //$this->output->writeln('Scenario: ' . $scenarioObject->getTitle());

            if (in_array($scenarioObject->getTitle(), $this->scenariosArray)) {
                $this->output->writeln('<bg=green;options=bold> SCENARIO </> <bg=gray>' . $scenarioObject->getTitle() . '</> IS in the '.$testFilename.' test file');

                if (array_key_exists($scenarioObject->getTitle(), $this->stepsArray)) {
                    $stepsArray = $this->stepsArray[$scenarioObject->getTitle()];
                } else {
                    $stepsArray = array();
                }

                // Check for steps
                $this->checkForMissingSteps($scenarioObject, $stepsArray, $testFilename);

            } else {
                $this->output->writeln('<bg=red;options=bold> SCENARIO </> "' . $scenarioObject->getTitle() . '" is NOT in the '.$testFilename.' test file');
                $this->errors++;
                // Check for steps (all will fail but we want the output)
                $this->checkForMissingSteps($scenarioObject, array(), $testFilename);
            }

        }

    }

    private function parseTestFile($testFile)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($testFile);

        $nodeFinder = new NodeFinder();
        $methods = $nodeFinder->findInstanceOf($ast, FuncCall::class);

        $this->featuresArray = [];
        $this->scenariosArray = [];
        $this->stepsArray = [];

        foreach($methods as $method) {

            $functionName = $method->name->parts[0];

            switch ($functionName) {
                case self::FEATURE:
                    $this->featuresArray[] = $method->args[0]->value->value;
                    break;
                case self::SCENARIO:
                    $this->scenariosArray[] = $method->args[0]->value->value;

                    $steps = $nodeFinder->findInstanceOf($method, FuncCall::class);

                    foreach($steps as $step) {

                        $stepIdentifier = $step->name->parts[0];
                        $stepName = substr($stepIdentifier, 5);

                        if(substr($stepIdentifier, 0, 5) == self::STEP) {

                            $cleanedStepName = str_replace('_', ' ', $stepName);
                            $this->stepsArray[$method->args[0]->value->value][] = $cleanedStepName;
                        }

                    }
                    break;
            }

        }
    }

}
