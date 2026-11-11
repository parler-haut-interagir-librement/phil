<?php

declare(strict_types=1);

namespace Phil\UxCss\Tests\Unit\Stylesheet;

use Override;
use Phil\UxCss\Exception\UnreadableSheet;
use Phil\UxCss\Stylesheet\StylesheetBundler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(StylesheetBundler::class)]
final class StylesheetBundlerTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-ux-css-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/parts', recursive: true);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach ([$this->directory.'/parts/*', $this->directory.'/*.css'] as $pattern) {
            $files = glob($pattern);
            array_map(unlink(...), false === $files ? [] : $files);
        }

        rmdir($this->directory.'/parts');
        rmdir($this->directory);
    }

    public function testEachImportIntoALayerBecomesTheLayerHoldingTheFile(): void
    {
        file_put_contents($this->directory.'/parts/a.css', ":root {\n\n    --a: 1;\n}\n\n");
        file_put_contents($this->directory.'/b.css', '.isB { color: red; }');
        file_put_contents($this->directory.'/entry.css', implode("\n", [
            '/* entry */',
            '@layer one, two;',
            '@import url("./parts/a.css") layer(one);',
            '@import url("./b.css") layer(two.inner);',
            '@import url("./b.css");',
            '  @import url("./b.css") layer(two);',
            '',
        ]));

        self::assertSame(implode("\n", [
            '/* entry */',
            '@layer one, two;',
            '@layer one {',
            '    :root {',
            '',
            '        --a: 1;',
            '    }',
            '}',
            '@layer two.inner {',
            '    .isB { color: red; }',
            '}',
            '@import url("./b.css");',
            '  @import url("./b.css") layer(two);',
            '',
        ]), StylesheetBundler::bundle($this->directory.'/entry.css'));
    }

    public function testAMissingEntryOrImportIsUnreadable(): void
    {
        file_put_contents($this->directory.'/entry.css', '@import url("./nowhere.css") layer(x);');

        $this->expectException(UnreadableSheet::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The stylesheet "%s/./nowhere.css" cannot be read.', $this->directory));

        StylesheetBundler::bundle($this->directory.'/entry.css');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function importsOutsideTheEntry(): iterable
    {
        yield 'a parent directory' => ['../b.css'];
        yield 'a parent directory further down' => ['./a/../../b.css'];
        yield 'an empty segment' => ['a//../../b.css'];
        yield 'an absolute path' => ['%s/b.css'];
        yield 'a URL' => ['file://%s/b.css'];
    }

    public function testAnImportThatClimbsBackToTheDirectoryOfTheEntryIsRead(): void
    {
        file_put_contents($this->directory.'/b.css', '.isB { color: red; }');
        file_put_contents($this->directory.'/entry.css', '@import url("parts/.././b.css") layer(x);');

        self::assertSame("@layer x {\n    .isB { color: red; }\n}", StylesheetBundler::bundle($this->directory.'/entry.css'));
    }

    #[DataProvider('importsOutsideTheEntry')]
    public function testAnImportOutsideTheDirectoryOfTheEntryIsRefused(string $import): void
    {
        mkdir($this->directory.'/parts/a');
        file_put_contents($this->directory.'/b.css', '.isB { color: red; }');
        $path = \sprintf($import, $this->directory);
        file_put_contents($this->directory.'/parts/entry.css', \sprintf('@import url("%s") layer(x);', $path));

        try {
            $this->expectException(UnreadableSheet::class);
            $this->expectExceptionMessageIsOrContains(\sprintf('The stylesheet "%s" is outside the directory of the entry.', $path));

            StylesheetBundler::bundle($this->directory.'/parts/entry.css');
        } finally {
            rmdir($this->directory.'/parts/a');
        }
    }

    public function testAnImportOfADirectoryIsUnreadable(): void
    {
        file_put_contents($this->directory.'/entry.css', '@import url("./parts") layer(x);');

        $this->expectException(UnreadableSheet::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The stylesheet "%s/./parts" cannot be read.', $this->directory));

        StylesheetBundler::bundle($this->directory.'/entry.css');
    }

    public function testTheDistributedFileIsTheBundleOfTheSources(): void
    {
        self::assertStringEqualsFile(
            __DIR__.'/../../../dist/phil-ux.css',
            StylesheetBundler::bundle(__DIR__.'/../../../css/phil-ux.css'),
            'dist/phil-ux.css is stale: run php bin/build.php in the package',
        );
    }
}
