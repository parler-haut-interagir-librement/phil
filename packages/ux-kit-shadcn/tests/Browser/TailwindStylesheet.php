<?php

declare(strict_types=1);

namespace Phil\UxKitShadcn\Tests\Browser;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Compiles the stylesheet of the page with the Tailwind of tests/Browser/package.json, so the utilities of the recipes
 * reach the browser as they do in an application.
 */
final class TailwindStylesheet
{
    private const string CLI = __DIR__.'/node_modules/.bin/tailwindcss';

    private const string OUTPUT = __DIR__.'/node_modules/.phil-build/tailwind.css';

    /**
     * @return string The compiled file
     */
    public static function compile(): string
    {
        if (!is_file(self::CLI)) {
            throw new RuntimeException('Tailwind is missing: run `npm ci --ignore-scripts` in '.__DIR__.'.');
        }

        new Process([self::CLI, '--input', __DIR__.'/resources/app.css', '--output', self::OUTPUT], __DIR__)->mustRun();

        return self::OUTPUT;
    }
}
