<?php

declare(strict_types=1);

namespace Phil\Highlight\Tests\Unit\Language;

use Phil\Highlight\Language;
use Phil\Highlight\Language\Code;
use Phil\Highlight\Language\Definitions;
use Phil\Highlight\Language\Diff;
use Phil\Highlight\Language\Dockerfile;
use Phil\Highlight\Language\Ini;
use Phil\Highlight\Tests\Unit\Tokens;
use Phil\Highlight\Token;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Ini::class)]
#[CoversClass(Dockerfile::class)]
#[CoversClass(Diff::class)]
final class LineLanguagesTest extends TestCase
{
    public function testTheyHaveTheirNames(): void
    {
        self::assertSame(['ini', 'dosini', 'cfg', 'conf', 'properties', 'env', 'dotenv', 'editorconfig', 'gitconfig'], [$this->language('ini')->name(), ...$this->language('ini')->aliases()]);
        self::assertSame(['dockerfile', 'docker', 'containerfile'], [$this->language('dockerfile')->name(), ...$this->language('dockerfile')->aliases()]);
        self::assertSame(['diff', 'patch', 'udiff'], [$this->language('diff')->name(), ...$this->language('diff')->aliases()]);
    }

    private function language(string $name): Language
    {
        return match ($name) {
            'ini' => new Ini(),
            'dockerfile' => new Dockerfile(new Code(Definitions::bash())),
            default => new Diff(),
        };
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function sources(): iterable
    {
        yield 'ini: sections, keys, values and comments' => ['ini', "; top\n# also\n[section] ; c\n[open\nkey = value ; c\nn=1\nb: On\ncolor=#fff\ns = \"a;b\" # c\nq='x'\nempty =\nplain line\n  indented = 2;3",
            "{comment:; top}\n{comment:# also}\n{section:[section]} {comment:; c}\n{section:[open}\n{property:key} {operator:=} {string:value} {comment:; c}\n{property:n}{operator:=}{number:1}\n{property:b}{operator::} {constant:On}\n{property:color}{operator:=}{string:#fff}\n{property:s} {operator:=} {string:\"a;b\"} {comment:# c}\n{property:q}{operator:=}{string:'x'}\n{property:empty} {operator:=}\nplain line\n  {property:indented} {operator:=} {string:2;3}"];
        yield 'ini: a comment right after the sign' => ['ini', 'a = ;c',
            '{property:a} {operator:=} {comment:;c}'];
        yield 'dockerfile: instructions, comments and continued lines' => ['dockerfile', "# syntax=docker/dockerfile:1\nFROM php:8.5 AS base\n\nrun apt-get update \\\n  && echo ok\nCMD [\"a\", \"b\"]\nNOPE x\n  WORKDIR /app\nRUN a \\  \n  b\nRUN c \\",
            "{comment:# syntax=docker/dockerfile:1}\n{keyword:FROM} php:{number:8.5} AS base\n\n{keyword:run} apt-get update \\\n  {operator:&&} {builtin:echo} ok\n{keyword:CMD} {punctuation:[}{string:\"a\"}, {string:\"b\"}{punctuation:]}\nNOPE x\n  {keyword:WORKDIR} /app\n{keyword:RUN} a \\  \n  b\n{keyword:RUN} c \\"];
        yield 'diff: headers, hunks and lines' => ['diff', "diff --git a/x b/x\nindex 1..2\nIndex: x\n--- a/x\n+++ b/x\n*** c\n=== d\n@@ -1,2 +1,2 @@\n-old\n+new\n context\n< left\n> right\n\\ No newline at end of file",
            "{meta:diff --git a/x b/x}\n{meta:index 1..2}\n{meta:Index: x}\n{meta:--- a/x}\n{meta:+++ b/x}\n{meta:*** c}\n{meta:=== d}\n{section:@@ -1,2 +1,2 @@}\n{deleted:-old}\n{inserted:+new}\n context\n{deleted:< left}\n{inserted:> right}\n{comment:\\ No newline at end of file}"];
        yield 'ini: a value after a section' => ['ini', '[a] b ; c',
            '{section:[a]} {string:b} {comment:; c}'];
        yield 'ini: a quoted value holding a comment sign' => ['ini', 's = "a ;b" c',
            '{property:s} {operator:=} {string:"a ;b" c}'];
        yield 'dockerfile: a continued line that holds an instruction' => ['dockerfile', "RUN a \\  \nFROM b\nFROM c",
            "{keyword:RUN} a \\  \nFROM b\n{keyword:FROM} c"];
        yield 'ini: a doubled sign, and lines without a sign' => ['ini', "a == b\nc [d\ne ;f",
            "{property:a} {operator:=}{string:= b}\nc [d\ne ;f"];
    }

    #[DataProvider('sources')]
    public function testItTokenizes(string $language, string $source, string $expected): void
    {
        $tokens = $this->language($language)->tokenize($source);

        self::assertSame($expected, Tokens::render($tokens));
        self::assertSame($source, implode('', array_map(static fn (Token $token): string => $token->text, $tokens)));
    }
}
