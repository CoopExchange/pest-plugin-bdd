<?php

namespace Vmeretail\PestPluginBdd;

use PhpParser\Node\Expr\FuncCall;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

class PestParser
{
    const FEATURE = 'describe';
    const SCENARIO = 'it';
    const STEP = 'step_';

    public function parseTestFile(string $testFileContents) : array
    {
        $methods = $this->getMethods($testFileContents);

        $featuresArray = [];
        $scenariosArray = [];
        $scenariosOpenArray = [];
        $stepsArray = [];

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

        return array($featuresArray, $scenariosArray, $stepsArray, $scenariosOpenArray);

    }

    public function getDescribeDescription(string $testFileContents) : array
    {
        $methods = $this->getMethods($testFileContents);

        $describeDescriptionStartText = null;
        $describeDescriptionStartLine = null;
        $describeDescriptionEndLine = null;

        foreach($methods as $method) {

            $functionName = $method->name->parts[0];

            switch ($functionName) {
                case self::FEATURE:

                    //$featuresArray[$method->getEndLine()] = $method->args[0]->value->value;
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

    private function getMethods(string $testFileContents)
    {
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $ast = $parser->parse($testFileContents);

        $nodeFinder = new NodeFinder();
        return $nodeFinder->findInstanceOf($ast, FuncCall::class);
    }
}
