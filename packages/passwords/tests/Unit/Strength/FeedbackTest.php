<?php

declare(strict_types=1);

namespace Phil\Passwords\Tests\Unit\Strength;

use Phil\Passwords\Strength\Feedback;
use Phil\Passwords\Strength\Suggestion;
use Phil\Passwords\Strength\Warning;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Feedback::class)]
#[CoversClass(Warning::class)]
#[CoversClass(Suggestion::class)]
final class FeedbackTest extends TestCase
{
    public function testEmpty(): void
    {
        self::assertTrue(new Feedback()->isEmpty());
        self::assertFalse(new Feedback(Warning::Date)->isEmpty());
        self::assertFalse(new Feedback(null, [Suggestion::AddWord])->isEmpty());
    }

    public function testTranslationKeys(): void
    {
        self::assertSame('warning.top_ten', Warning::TopTen->translationKey());
        self::assertSame('suggestion.add_word', Suggestion::AddWord->translationKey());
    }
}
