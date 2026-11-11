<?php

declare(strict_types=1);

namespace Phil\Slug\Tests\Unit;

use Phil\Slug\InvalidSlug;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Slug::class)]
#[CoversClass(InvalidSlug::class)]
final class SlugTest extends TestCase
{
    public function testASlugKeepsItsSpelling(): void
    {
        $slug = Slug::fromString('my-first-article-2');

        self::assertSame(['my-first-article-2', 'my-first-article-2', 'my-first-article-2'], [$slug->value, $slug->toString(), (string) $slug]);
        self::assertTrue($slug->equals(Slug::fromString('my-first-article-2')));
        self::assertFalse($slug->equals(Slug::fromString('my-first-article')));
        self::assertSame(['a', str_repeat('a', 255)], [Slug::fromString('a')->value, Slug::fromString(str_repeat('a', 255))->value]);
        self::assertSame(255, Slug::MAX_LENGTH);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function notSlugs(): iterable
    {
        yield 'too long' => [str_repeat('a', 256), 'A slug is at most 255 characters long.'];
        yield 'empty' => ['', '"" is not a slug of lowercase words joined by hyphens.'];
        yield 'capitals' => ['My-Article', '"My-Article" is not a slug of lowercase words joined by hyphens.'];
        yield 'two hyphens' => ['a--b', '"a--b" is not a slug of lowercase words joined by hyphens.'];
        yield 'leading hyphen' => ['-a', '"-a" is not a slug of lowercase words joined by hyphens.'];
        yield 'trailing hyphen' => ['a-', '"a-" is not a slug of lowercase words joined by hyphens.'];
        yield 'an accent' => ['été', '"été" is not a slug of lowercase words joined by hyphens.'];
        yield 'a new line' => ["a\n", "\"a\n\" is not a slug of lowercase words joined by hyphens."];
        yield 'a space' => ['a b', '"a b" is not a slug of lowercase words joined by hyphens.'];
    }

    #[DataProvider('notSlugs')]
    public function testWhatIsNotASlugIsRefused(string $value, string $message): void
    {
        $this->expectException(InvalidSlug::class);
        $this->expectExceptionMessageIsOrContains($message);

        Slug::fromString($value);
    }
}
