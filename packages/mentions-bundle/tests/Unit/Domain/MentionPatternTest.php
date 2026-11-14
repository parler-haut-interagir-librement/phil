<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Domain;

use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\ValueObject\MentionPattern;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(MentionPattern::class)]
final class MentionPatternTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function texts(): iterable
    {
        yield 'a handle' => ['Hi @ada!', ['ada']];
        yield 'at the start' => ['@ada', ['ada']];
        yield 'a full stop ends the handle' => ['Thanks @ada.', ['ada']];
        yield 'a hyphen at the end ends it' => ['@bob-', ['bob']];
        yield 'dots and hyphens inside' => ['@jean-luc.picard', ['jean-luc.picard']];
        yield 'in parentheses' => ['(@ada, @bob)', ['ada', 'bob']];
        yield 'not after a word' => ['ada@example.com', []];
        yield 'not after a digit' => ['1@ada', []];
        yield 'not after an underscore' => ['_@ada', []];
        yield 'not escaped' => ['\@ada', []];
        yield 'not doubled' => ['@@ada', []];
        yield 'not in a path' => ['https://example.test/@ada', []];
        yield 'not alone' => ['@ and @.', []];
        yield '64 characters at most' => ['@'.str_repeat('a', 70), [str_repeat('a', 64)]];
        yield 'after a letter of any script' => ['é@ada', []];
    }

    /**
     * @param list<string> $handles
     */
    #[DataProvider('texts')]
    public function testTheDefaultPattern(string $text, array $handles): void
    {
        $pattern = MentionPattern::default();
        preg_match_all($pattern->regex, $text, $matches);

        self::assertSame($handles, $matches['handle']);
        self::assertSame(MentionPattern::DEFAULT, $pattern->handle);
    }

    public function testAPatternOfTheApplication(): void
    {
        $pattern = MentionPattern::of('\p{L}[\p{L}\p{N}_]{0,31}');
        preg_match_all($pattern->regex, 'Bonjour @Élodie_2 et @9lives', $matches);

        self::assertSame(['Élodie_2'], $matches['handle']);
        self::assertSame('~(?<![\p{L}\p{N}_@\\\\/])@(?<handle>\p{L}[\p{L}\p{N}_]{0,31})~u', $pattern->regex);
        self::assertSame(str_repeat('a', 256), MentionPattern::of(str_repeat('a', 256))->handle);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => ['', 'The pattern of a handle is 1 to 256 bytes.'];
        yield 'too long' => [str_repeat('a', 257), 'The pattern of a handle is 1 to 256 bytes.'];
        yield 'a tilde' => ['a~b', 'The pattern of a handle holds no "~" nor a group named "handle".'];
        yield 'a group named handle' => ['(?<handle>a)', 'The pattern of a handle holds no "~" nor a group named "handle".'];
        yield 'a Python group named handle' => ['(?P<handle>a)', 'The pattern of a handle holds no "~" nor a group named "handle".'];
        yield 'invalid' => ['[a-', 'The pattern of a handle "[a-" is not a valid regular expression.'];
        yield 'escaping its group' => ['a)|(?:b', 'The pattern of a handle "a)|(?:b" is not a valid regular expression.'];
        yield 'quoting the rest' => ['\Qa', 'The pattern of a handle "\Qa" is not a valid regular expression.'];
        yield 'matching nothing' => ['a*', 'The pattern of a handle "a*" matches an empty handle.'];
    }

    #[DataProvider('refused')]
    public function testARefusedPattern(string $handle, string $message): void
    {
        try {
            MentionPattern::of($handle);
            self::fail($message);
        } catch (InvalidMention $invalidMention) {
            self::assertSame($message, $invalidMention->getMessage());
        }
    }
}
