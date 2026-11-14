<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Redaction;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Redaction\CommandLineRedactor;
use Phil\PrivacyBundle\Domain\Redaction\Denylist;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommandLineRedactor::class)]
#[UsesClass(Denylist::class)]
#[UsesClass(InvalidPrivacyValue::class)]
final class CommandLineRedactorTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function redactedLines(): iterable
    {
        yield 'an inline value' => ['app:import --password=hunter2', 'app:import --password=[redacted]'];
        yield 'a separate value' => ['app:import --token abc123 --force', 'app:import --token [redacted] --force'];
        yield 'a separate value after several spaces' => ['app:import --secret   s3', 'app:import --secret   [redacted]'];
        yield 'an empty inline value' => ['app:import --password= --force', 'app:import --password=[redacted] --force'];
        yield 'a name the denylist normalises' => ['app:x --Pass_Word=a', 'app:x --Pass_Word=[redacted]'];
        yield 'a name a pattern takes' => ['app:x --db-password=a --api-token b', 'app:x --db-password=[redacted] --api-token [redacted]'];
        yield 'a single-quoted value with a space' => ["app:x --password='a b' --force", 'app:x --password=[redacted] --force'];
        yield 'the quoting Symfony writes for an apostrophe' => ["app:x --password='it'\\''s' --force", 'app:x --password=[redacted] --force'];
        yield 'a double-quoted value with an escaped quote' => ['app:x --password="a \" b" --force', 'app:x --password=[redacted] --force'];
        yield 'a backslash escape in a plain value' => ['app:x --password=a\ b --force', 'app:x --password=[redacted] --force'];
        yield 'a command line quoted in a message' => [
            'Error thrown while running command "app:x --password=hunter2". Message: "boom"',
            'Error thrown while running command "app:x --password=[redacted]". Message: "boom"',
        ];
        yield 'an option at the start of the text' => ['--secret=x', '--secret=[redacted]'];
        yield 'an option after an equals sign' => ['argv=--secret=x', 'argv=--secret=[redacted]'];
        yield 'an option after a parenthesis' => ['(--token "x")', '(--token [redacted])'];
        // A plain run takes `)` with it: a shell word that holds one is quoted, so the run errs towards too much.
        yield 'a closing parenthesis after a plain value' => ['(--token x)', '(--token [redacted]'];
        yield 'an option after a newline' => ["first\n--token x", "first\n--token [redacted]"];
        yield 'a double quote that never closes' => ['app:x --password="a b --force', 'app:x --password=[redacted]'];
        yield 'a single quote that never closes' => ["app:x --token 'a b --force", 'app:x --token [redacted]'];
        yield 'a separate single-quoted value' => ["app:x --token 'a b' --force", 'app:x --token [redacted] --force'];
        yield 'a separate value that starts with a dash' => ['app:x --password -abc --force', 'app:x --password [redacted] --force'];
        yield 'a separate value that is a single dash' => ['app:x --token - file', 'app:x --token [redacted] file'];
        yield 'every occurrence' => ['--token=a --token=b', '--token=[redacted] --token=[redacted]'];
        yield 'an option quoted in the value of an option kept' => ['app:x --message "--password=x"', 'app:x --message "--password=[redacted]"'];
        yield 'an option inline in the value of an option kept' => ['app:x --args=--token=y', 'app:x --args=--token=[redacted]'];
    }

    #[DataProvider('redactedLines')]
    public function testTheValueOfADeniedOptionIsRedacted(string $line, string $expected): void
    {
        self::assertSame($expected, $this->redactor()->redact($line));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function untouchedLines(): iterable
    {
        yield 'no option' => ['app:import users.csv'];
        yield 'an option that is not denied' => ['app:import --env=prod --limit 10 --dry-run'];
        yield 'a denied flag followed by another option' => ['app:import --token --force'];
        yield 'a denied flag at the end' => ['app:import --token'];
        yield 'a denied flag followed by a double dash' => ['app:import --token -- file'];
        yield 'a denied name inside a word' => ['not--password=x'];
        yield 'a short option without a table' => ['app:import -p hunter2'];
        yield 'a dash run that is not an option' => ['---- --- -- ='];
        yield 'an option name that does not start with a letter or digit' => ['app:x --_password=a'];
    }

    #[DataProvider('untouchedLines')]
    public function testAnythingElseIsLeftAsItIs(string $line): void
    {
        self::assertSame($line, $this->redactor()->redact($line));
    }

    public function testAPlainValueStopsAtADoubleQuote(): void
    {
        self::assertSame('"--token=[redacted]" and "kept"', $this->redactor()->redact('"--token=abc" and "kept"'));
    }

    public function testTheValueOfANonDeniedOptionIsKeptWhole(): void
    {
        self::assertSame("app:x --name='a b' --label \"c d\"", $this->redactor()->redact("app:x --name='a b' --label \"c d\""));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function longValues(): iterable
    {
        foreach (['20 KB' => 20_000, '1 MB' => 1_048_576] as $size => $length) {
            yield 'a plain value of '.$size => [str_repeat('a', $length)];
            yield 'a double-quoted value of '.$size => ['"'.str_repeat('a', $length).'"'];
            yield 'a single-quoted value of '.$size => ["'".str_repeat('a', $length)."'"];
            yield 'a plain value of escapes of '.$size => [str_repeat('\\a', intdiv($length, 2))];
            yield 'a double-quoted value of escapes of '.$size => ['"'.str_repeat('\\a', intdiv($length, 2)).'"'];
            yield 'a value of alternating quotes of '.$size => [str_repeat("'a'b", intdiv($length, 4))];
        }
    }

    /**
     * A value of any length is read without backtracking: it is redacted and the rest of the line kept, never the
     * whole line turned into the placeholder.
     */
    #[DataProvider('longValues')]
    public function testALongValueIsRedactedAndTheRestKept(string $value): void
    {
        self::assertSame(
            'app:x --password=[redacted] --name '.$value.' --token [redacted] --force',
            $this->redactor()->redact('app:x --password='.$value.' --name '.$value.' --token '.$value.' --force'),
        );
        self::assertSame(
            'app:x -p [redacted] --force',
            $this->redactor(self::SHORT_OPTIONS)->redact('app:x -p '.$value.' --force'),
        );
    }

    public function testAFailingPatternReturnsThePlaceholder(): void
    {
        $limit = \ini_get('pcre.backtrack_limit');
        ini_set('pcre.backtrack_limit', '1');

        try {
            self::assertSame('[redacted]', $this->redactor()->redact('--password="'.str_repeat('a', 10_000).'"'));
        } finally {
            ini_set('pcre.backtrack_limit', false === $limit ? '1000000' : $limit);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shortOptionLines(): iterable
    {
        yield 'a separate value' => ['app:x -p hunter2 --force', 'app:x -p [redacted] --force'];
        yield 'an attached value' => ['app:x -phunter2 --force', 'app:x -p[redacted] --force'];
        yield 'an inline value' => ['app:x -p=hunter2 --force', 'app:x -p=[redacted] --force'];
        yield 'an empty inline value' => ['app:x -p= --force', 'app:x -p=[redacted] --force'];
        yield 'a quoted separate value' => ["app:x -p 'a b' --force", 'app:x -p [redacted] --force'];
        yield 'a separate value that starts with a dash' => ['app:x -p -abc', 'app:x -p [redacted]'];
        yield 'the letter closing a set of flags' => ['app:x -vp hunter2', 'app:x -vp [redacted]'];
        yield 'an attached value after a set of flags' => ['app:x -vphunter2', 'app:x -vp[redacted]'];
        yield 'a name the denylist takes by pattern' => ['app:x -t abc', 'app:x -t [redacted]'];
        yield 'in a quoted command line' => ['Error thrown while running command "app:x -p hunter2".', 'Error thrown while running command "app:x -p [redacted]".'];
        yield 'after a long option' => ['app:x --password=a -p b', 'app:x --password=[redacted] -p [redacted]'];
        yield 'in the value of a long option kept' => ['app:x --message "-p b"', 'app:x --message "-p [redacted]"'];
    }

    #[DataProvider('shortOptionLines')]
    public function testADeclaredShortOptionOfADeniedNameIsRedacted(string $line, string $expected): void
    {
        self::assertSame($expected, $this->redactor(self::SHORT_OPTIONS)->redact($line));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function shortOptionsKept(): iterable
    {
        yield 'a letter mapped to a name not denied' => ['app:x -P 8080'];
        yield 'a letter not declared' => ['app:x -x hunter2'];
        yield 'a flag followed by a long option' => ['app:x -p --force'];
        yield 'a flag at the end' => ['app:x -p'];
        yield 'a flag followed by a double dash' => ['app:x -p -- file'];
        yield 'a dash inside a word' => ['re-print x-p y'];
        yield 'a long option that starts with the letter' => ['app:x --pretty'];
    }

    #[DataProvider('shortOptionsKept')]
    public function testAnyOtherShortOptionIsKept(string $line): void
    {
        self::assertSame($line, $this->redactor(self::SHORT_OPTIONS)->redact($line));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function malformedShortOptions(): iterable
    {
        yield 'two letters' => [['pw' => 'password'], '"pw" is not a short option'];
        yield 'a digit' => [[1 => 'password'], '"1" is not a short option'];
        yield 'a dash' => [['-p' => 'password'], '"-p" is not a short option'];
        yield 'a name with its dashes' => [['p' => '--password'], 'The short option "p" must map to a long option name'];
        yield 'an empty name' => [['p' => ''], 'The short option "p" must map to a long option name'];
        yield 'a name that is not a string' => [['p' => 5], 'The short option "p" must map to a long option name'];
    }

    /**
     * @param array<array-key, mixed> $table
     */
    #[DataProvider('malformedShortOptions')]
    public function testAMalformedShortOptionIsRefused(array $table, string $message): void
    {
        $this->expectException(InvalidPrivacyValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $this->redactor($table);
    }

    public function testAFailingShortOptionPatternReturnsThePlaceholder(): void
    {
        $limit = \ini_get('pcre.backtrack_limit');
        $redactor = $this->redactor(self::SHORT_OPTIONS);
        ini_set('pcre.backtrack_limit', '1');

        try {
            self::assertSame('[redacted]', $redactor->redact('-p "'.str_repeat('a', 10_000).'"'));
        } finally {
            ini_set('pcre.backtrack_limit', false === $limit ? '1000000' : $limit);
        }
    }

    private const array SHORT_OPTIONS = ['p' => 'password', 't' => 'api-token', 'P' => 'port', 'x' => 'exclude'];

    /**
     * @param array<array-key, mixed> $shortOptions
     */
    private function redactor(array $shortOptions = []): CommandLineRedactor
    {
        return new CommandLineRedactor(Denylist::of('password', 'secret', '*token*', '*password*'), $shortOptions);
    }
}
