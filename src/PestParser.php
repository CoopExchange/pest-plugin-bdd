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
            $cleanedStepName = str_replace('step_', '', $function->name->name);
            $cleanedStepName = str_replace('_', ' ', $cleanedStepName);
            $stepsOpenArray[$function->name->getStartLine()] = $cleanedStepName;
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

                        $stepName = substr($stepIdentifier, 5);

                        if(substr($stepIdentifier, 0, 5) == self::STEP) {

                            $cleanedStepName = str_replace('_', ' ', $stepName);

                            $stepsArray[$method->args[0]->value->value][] = $cleanedStepName;
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
}
