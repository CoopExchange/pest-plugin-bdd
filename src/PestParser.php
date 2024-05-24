<?php

declare(strict_types=1);

namespace CoopExchange\PestPluginBdd;

use Exception;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use Spatie\LaravelData\DataCollection;
use CoopExchange\PestPluginBdd\Models\PestScenario;
use CoopExchange\PestPluginBdd\Models\PestStep;

final class PestParser
{
    public function getDescribeNames(string $testFileContents): Collection
    {
        $methods = new Collection($this->getFuncCallMethods($testFileContents));

        return $methods
            ->filter(fn($method) => $method->name?->name === 'describe')
            ->map(function ($describe) {
                try {
                    return $describe->args[0]->value?->value;
                } catch (Exception $e) {
                    return '';
                }
            })
            ->values();
    }

    private function getFuncCallMethods(string $testFileContents): array
    {
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($testFileContents);

        $nodeFinder = new NodeFinder();
        return $nodeFinder->findInstanceOf($ast, FuncCall::class);
    }
}
