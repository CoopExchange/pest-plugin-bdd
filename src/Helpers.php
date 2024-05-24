<?php

namespace CoopExchange\PestPluginBdd;

final class Helpers
{
    public static function newLine(): string
    {
        return PHP_EOL;
    }

    public static function indent(): string
    {
        return chr(9);
    }
}
