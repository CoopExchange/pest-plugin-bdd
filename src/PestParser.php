<?php

namespace Vmeretail\PestPluginBdd;

use Illuminate\Support\Collection;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Spatie\LaravelData\DataCollection;
use Vmeretail\PestPluginBdd\Models\PestScenario;
use Vmeretail\PestPluginBdd\Models\PestStep;

class PestParser
{
    const FEATURE = 'describe';
    const SCENARIO = 'it';
    const STEP = 'step_';
    const BACKGROUND = 'beforeEach';

    public function getStepsOpen(string $testFileContents) : Collection
    {
        return collect($this->getFunctionMethods($testFileContents))
            ->mapWithKeys(function (Function_ $item) {
            return [$item->name->getStartLine() => $item->name->name];
        });
    }

    public function getFeatureEndLineNumber(string $testFileContents) : int
    {
        return $this->getFeatures($testFileContents)
            ->keys()
            ->first();
    }

    public function getFeatures(string $testFileContents) : Collection
    {
        return collect($this->getFuncCallMethods($testFileContents))
            // Filter to only Features
            ->filter(function (FuncCall $item) {
                return $item->name->parts[0] == self::FEATURE;
            })
            ->mapWithKeys(function (FuncCall $item) {
                return [$item->getEndLine() => $item->args[0]->value->value];
            });
    }

    public function getScenarios(string $testFileContents) : Collection
    {
        // TODO Add the beforeEach to this collection

        $ee = $this->getFuncCallMethods($testFileContents);
        foreach ($ee as $e) {

            //ray('BEE1', $e->name->getParts());

        }

        // This returns the functions but check its a step
        $stepFunctions = collect($this->getFunctionMethods($testFileContents))
            ->mapWithKeys(function (Function_ $item) {
                return [$item->name->getStartLine() => $item->name->name];
            });

        $scenarios = collect($this->getFuncCallMethods($testFileContents))
            // Filter to only Scenarios
            ->filter(function (FuncCall $item) {
                if($item->name->parts[0] == self::SCENARIO || $item->name->parts[0] == self::BACKGROUND) {
                    return $item;
                };
            })
        ->map(function ($item) use ($stepFunctions) {

            $arr = [];
            $nodeFinder = new NodeFinder();

            $steps = $nodeFinder->findInstanceOf($item, FuncCall::class);
            foreach($steps as $step) {
                $stepIdentifier = $step->name->parts[0];
                if(substr($stepIdentifier, 0, 5) == self::STEP) {

                    $stepFunction = $stepFunctions
                        ->filter(function ($item) use ($stepIdentifier) {
                        return $item == $stepIdentifier;
                    })
                        ->keys()
                        ->first();

                    //ray('TTT1', $stepIdentifier, $stepFunction);

                    $arr[] = new PestStep(
                        name: $stepIdentifier,
                        startLine: $step->getStartLine(),
                        endLine:  $step->getEndLine(),
                        functionStartLine: $stepFunction
                    );
                }
            }

            $steps = new DataCollection(PestStep::class, $arr);

            //ray('GETPARTS', $item->name->getParts()[0]);

            if($item->name->getParts()[0] == self::BACKGROUND) {
                $pestScenarioName = self::BACKGROUND;
            } else {
                $pestScenarioName = $item->args[0]->value->value;
            }

            $pestScenario = new PestScenario(
                name: $pestScenarioName,
                startLine: $item->getStartLine(),
                endLine: $item->getEndLine(),
                steps: $steps
            );

            return $pestScenario;
        })
        ;

        //ray('S3', $scenarios, $stepFunctions);

        return($scenarios);

    }

    public function getScenarioOpens(string $testFileContents) : Collection
    {
        return collect($this->getFuncCallMethods($testFileContents))
            // Filter to only Scenarios
            ->filter(function (FuncCall $item) {
                return $item->name->parts[0] == self::SCENARIO;
            })
            ->mapWithKeys(function (FuncCall $item) {
                return [$item->getStartLine() => $item->args[0]->value->value];
            });
    }

    public function parseTestFileIntoArrays(string $testFileContents) : array
    {
        $methods = $this->getFuncCallMethods($testFileContents);
        $functions = $this->getFunctionMethods($testFileContents);

        //ray('FEVER1', $methods[1], $functions);

        $featuresArray = [];
        $scenariosArray = [];
        $scenariosOpenArray = [];
        $stepsArray = [];
        $stepsOpenArray = [];

        foreach($functions as $function) {
            $stepsOpenArray[$function->name->getStartLine()] = $function->name->name;
        }

        //ray('KEYED2', $stepsOpenArray);

        foreach($methods as $method) {

            $functionName = $method->name->parts[0];

            switch ($functionName) {
                case self::FEATURE:
                    $featuresArray[$method->getEndLine()] = $method->args[0]->value->value;
                    break;
                case self::BACKGROUND:
                    $scenariosArray[$method->getEndLine()] = self::BACKGROUND;
                    $scenariosOpenArray[$method->getStartLine()] = self::BACKGROUND;

                    $nodeFinder = new NodeFinder();
                    $steps = $nodeFinder->findInstanceOf($method, FuncCall::class);

                    foreach($steps as $step) {

                        $stepIdentifier = $step->name->parts[0];

                        if(substr($stepIdentifier, 0, 5) == self::STEP) {

                            $stepsArray[self::BACKGROUND][$step->getEndLine()] = $stepIdentifier;
                        }

                    }
                    break;

                case self::SCENARIO:

                    $scenariosArray[$method->getEndLine()] = $method->args[0]->value->value;
                    $scenariosOpenArray[$method->getStartLine()] = $method->args[0]->value->value;

                    $nodeFinder = new NodeFinder();
                    $steps = $nodeFinder->findInstanceOf($method, FuncCall::class);

                    foreach($steps as $step) {

                        $stepIdentifier = $step->name->parts[0];

                        if(substr($stepIdentifier, 0, 5) == self::STEP) {

                            $stepsArray[$method->args[0]->value->value][$step->getEndLine()] = $stepIdentifier;
                        }

                    }
                    break;
            }

        }

        //ray('PARSER45', $featuresArray, $scenariosArray, $stepsArray, $scenariosOpenArray, $stepsOpenArray);

        return array($featuresArray, $scenariosArray, $stepsArray, $scenariosOpenArray, $stepsOpenArray);

    }

    public function getDescribeDescription(string $testFileContents) : array
    {
        $methods = $this->getFuncCallMethods($testFileContents);

        $describeDescriptionStartText = null;
        $describeDescriptionStartLine = null;
        $describeDescriptionEndLine = null;

        foreach($methods as $method) {

            $functionName = $method->name->parts[0];

            switch ($functionName) {
                case self::FEATURE:

                    $describeDescriptionObject = $method->args[1]->value->stmts[0]->getComments();

                    if(count($describeDescriptionObject) !== 0) {
                        $describeDescription = $method->args[1]->value->stmts[0]->getComments()[0];
                        $describeDescriptionStartText = $describeDescription->getText();
                        $describeDescriptionStartLine = $describeDescription->getStartLine();
                        $describeDescriptionEndLine = $describeDescription->getEndLine();
                    }
                    break;
            }

        }

        return array($describeDescriptionStartText, $describeDescriptionStartLine, $describeDescriptionEndLine);
    }

    private function getFuncCallMethods(string $testFileContents)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($testFileContents);

        $nodeFinder = new NodeFinder();
        return $nodeFinder->findInstanceOf($ast, FuncCall::class);
    }

    private function getFunctionMethods(string $testFileContents)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($testFileContents);

        $nodeFinder = new NodeFinder();
        return $nodeFinder->findInstanceOf($ast, Function_::class);
    }

    public function removeExistingDescriptionFromPestFile(array $describeDescription, Collection $editedTestFileLines) : Collection
    {
        if (!is_null($describeDescription[0])) {
            $currentLine = $describeDescription[1] -1;
            $endLine = $describeDescription[2];

            while($currentLine < $endLine) {
                $editedTestFileLines->forget($currentLine);
                $currentLine++;
            }

        }

        return $editedTestFileLines;
    }

    public function removeExistingDataFromStep(int $startLine, Collection $editedTestFileLines) : Collection
    {
        $currentLine = $startLine;
        $endLine = count($editedTestFileLines);

        while($currentLine <= $endLine) {

            if (trim($editedTestFileLines[$currentLine]) == '];') {
                $endLine = $currentLine;
            }
            if (trim($editedTestFileLines[$currentLine]) != '{') {
                //unset($editedTestFileLines[$currentLine]);
                $editedTestFileLines->forget($currentLine);
            }
            $currentLine++;
        }

        return $editedTestFileLines;
    }

    public function deleteExistingDataset(Collection $editedTestFileLines, int $scenarioEndLineNumber) : Collection
    {
        foreach($editedTestFileLines as $key => $editedTestFileLine) {

            if($key >= ($scenarioEndLineNumber) && trim($editedTestFileLine) == "]);") {

                $currentLine = ($scenarioEndLineNumber);
                while($currentLine <= $key) {
                    //unset($editedTestFileLines[$currentLine]);
                    $editedTestFileLines->forget($currentLine);
                    $currentLine++;
                }
                break;
            }

        }

        return $editedTestFileLines;
    }
}
