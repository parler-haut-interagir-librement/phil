<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\Rule\SubjectLength;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SubjectLength::class)]
final class SubjectLengthTest extends RuleTestCase
{
    public function testTheRuleIsNamedAndDefaultsTo50Characters(): void
    {
        self::assertSame('subject-length', new SubjectLength()->name());
        self::assertSame(50, new SubjectLength()->length);
    }

    public function testASubjectOf50CharactersPasses(): void
    {
        self::assertSame([], self::findings(new SubjectLength(), subject: '  '.str_repeat('a', 50).'  '));
        self::assertSame([], self::findings(new SubjectLength(), subject: ''));
    }

    public function testALongerSubjectIsAWarning(): void
    {
        $subject = str_repeat('a', 51);

        self::assertSame(['[warning] subject-length: The subject holds 51 characters; a list of messages shows about 50. ('.$subject.')'], self::findings(new SubjectLength(), subject: $subject));
    }

    public function testCharactersAreCountedAsAReaderSeesThem(): void
    {
        // « é » written as « e » and a combining accent, and a family emoji of seven code points: one character each.
        $subject = str_repeat("e\u{301}", 48)."\u{1F468}\u{200D}\u{1F469}\u{200D}\u{1F467}\u{200D}\u{1F466}".'!';

        self::assertSame([], self::findings(new SubjectLength(), subject: $subject));
        self::assertCount(1, self::findings(new SubjectLength(49), subject: $subject));
    }

    public function testAnInvalidSubjectIsCountedByCodePoint(): void
    {
        self::assertSame([], self::findings(new SubjectLength(3), subject: "ab\xFF"));
        self::assertSame(["[warning] subject-length: The subject holds 3 characters; a list of messages shows about 2. (ab\xFF)"], self::findings(new SubjectLength(2), subject: "ab\xFF"));
        // Two letters of two bytes each and an invalid byte: three code points, five bytes.
        self::assertSame([], self::findings(new SubjectLength(3), subject: "\u{E9}\u{E9}\xFF"));
    }
}
