<?php

namespace Vmeretail\PestPluginBdd;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class PestParser
{
    const FEATURE = 'describe';
    const SCENARIO = 'it';
    const STEP = 'step_';

    public function parseTestFile(string $testFileContents) : array
    {
        $methods = $this->getFuncCallMethods($testFileContents);
        $functions = $this->getFunctionMethods($testFileContents);

        $featuresArray = [];
        $scenariosArray = [];
        $scenariosOpenArray = [];
        $stepsArray = [];
        $stepsOpenArray = [];

        foreach($functions as $function) {
            $stepsOpenArray[$function->name->getStartLine()] = $function->name->name;
        }

        foreach($methods as $method) {

            $functionName = $method->name->parts[0];

            switch ($functionName) {
                case self::FEATURE:
                    $featuresArray[$method->getEndLine()] = $method->args[0]->value->value;
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

    public function removeExistingDescriptionFromPestFile(array $describeDescription, array $editedTestFileLines) : array
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

    public function removeExistingDataFromStep(int $startLine, array $editedTestFileLines) : array
    {
        $currentLine = $startLine;
        $endLine = count($editedTestFileLines);

        while($currentLine <= $endLine) {

            if (trim($editedTestFileLines[$currentLine]) == '];') {
                $endLine = $currentLine;
            }
            if (trim($editedTestFileLines[$currentLine]) != '{') {
                unset($editedTestFileLines[$currentLine]);
            }
            $currentLine++;
        }

        return $editedTestFileLines;
    }

    public function deleteExistingDataset(array $editedTestFileLines, int $scenarioEndLineNumber) : array
    {
        foreach($editedTestFileLines as $key => $editedTestFileLine) {

            if($key >= ($scenarioEndLineNumber) && trim($editedTestFileLine) == "]);") {

                $currentLine = ($scenarioEndLineNumber);
                while($currentLine <= $key) {
                    unset($editedTestFileLines[$currentLine]);
                    $currentLine++;
                }
                break;
            }

        }

        return $editedTestFileLines;
    }
}
