<?php

declare(strict_types=1);

namespace Phil\Publishing\Tests\Unit;

use Phil\Publishing\Contract\PageEvent;
use Phil\Publishing\Exception\InvalidPageEvent;
use Phil\Publishing\PageEventFields;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(PagePublished::class)]
#[CoversClass(PageRetired::class)]
#[CoversClass(PageEventFields::class)]
#[CoversClass(InvalidPageEvent::class)]
final class PageEventTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<PagePublished|PageRetired>}>
     */
    public static function events(): iterable
    {
        yield 'published' => [PagePublished::class];
        yield 'retired' => [PageRetired::class];
    }

    /**
     * @param class-string<PagePublished|PageRetired> $class
     */
    #[DataProvider('events')]
    public function testItCarriesTheFourFieldsOfTheContract(string $class): void
    {
        $url = Url::fromString('https://example.org/fr/news/opening');
        $event = new $class($url, 'fr-CA', true, 'news:42');

        self::assertInstanceOf(PageEvent::class, $event);
        self::assertSame($url, $event->canonicalUrl);
        self::assertSame('fr-CA', $event->locale);
        self::assertTrue($event->indexable);
        self::assertSame('news:42', $event->ownerId);
        self::assertFalse(new $class($url, 'fr', false, 'news:42')->indexable);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function languageTags(): iterable
    {
        yield 'a language' => ['fr'];
        yield 'three letters' => ['ast'];
        yield 'a region' => ['fr-CA'];
        yield 'a script and a region' => ['zh-Hant-TW'];
        yield 'upper case' => ['EN-us'];
        yield 'a numeric region' => ['es-419'];
        yield 'a one-character subtag' => ['de-x-a'];
        yield 'eight characters' => ['de-1234abcd'];
        yield 'at the length limit' => ['en-'.str_repeat('a', 8).'-'.str_repeat('b', 8).'-'.str_repeat('c', 8).'-abcde'];
    }

    #[DataProvider('languageTags')]
    public function testItAcceptsALanguageTag(string $tag): void
    {
        self::assertSame($tag, new PagePublished(Url::fromString('https://example.org/'), $tag, true, 'page:1')->locale);
        self::assertSame($tag, new PageRetired(Url::fromString('https://example.org/'), $tag, true, 'page:1')->locale);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLanguageTags(): iterable
    {
        yield 'empty' => [''];
        yield 'one letter' => ['f'];
        yield 'four letters' => ['fren'];
        yield 'an ICU identifier' => ['fr_CA'];
        yield 'a trailing hyphen' => ['fr-'];
        yield 'a digit first' => ['1fr'];
        yield 'a subtag of nine characters' => ['fr-123456789'];
        yield 'a new line at the end' => ["fr\n"];
        yield 'a space' => ['fr CA'];
        yield 'too long' => ['en-'.str_repeat('a', 8).'-'.str_repeat('b', 8).'-'.str_repeat('c', 8).'-abcdef'];
        yield 'a prefix' => ['xfr-CA!'];
    }

    #[DataProvider('notLanguageTags')]
    public function testItRefusesWhatIsNotALanguageTag(string $tag): void
    {
        $this->expectException(InvalidPageEvent::class);
        $this->expectExceptionMessageIs(\sprintf('"%s" is not a BCP 47 language tag, as "fr" or "fr-CA".', mb_substr($tag, 0, 40)));

        new PagePublished(Url::fromString('https://example.org/'), $tag, true, 'page:1');
    }

    public function testARetiredPageRefusesWhatIsNotALanguageTag(): void
    {
        $this->expectException(InvalidPageEvent::class);

        new PageRetired(Url::fromString('https://example.org/'), 'fr_CA', true, 'page:1');
    }

    public function testTheMessageCutsALongLanguage(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('a', 40).'" is not');

        new PagePublished(Url::fromString('https://example.org/'), str_repeat('a', 50), true, 'page:1');
    }

    public function testTheMessageCutsALongLanguageByCharacters(): void
    {
        $this->expectException(InvalidPageEvent::class);
        $this->expectExceptionMessageIs('"'.str_repeat('é', 40).'" is not a BCP 47 language tag, as "fr" or "fr-CA".');

        new PagePublished(Url::fromString('https://example.org/'), str_repeat('é', 50), true, 'page:1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function ownerIds(): iterable
    {
        yield 'a reference' => ['news:0190f3a2-7c1e-7b5a-9d2e-1a2b3c4d5e6f'];
        yield 'one character' => ['1'];
        yield 'inner spaces' => ['a page'];
        yield 'at the length limit' => [str_repeat('a', 255)];
        yield 'accented' => ['pages:été'];
    }

    #[DataProvider('ownerIds')]
    public function testItAcceptsAnOwnerIdentifier(string $ownerId): void
    {
        self::assertSame($ownerId, new PagePublished(Url::fromString('https://example.org/'), 'fr', true, $ownerId)->ownerId);
        self::assertSame($ownerId, new PageRetired(Url::fromString('https://example.org/'), 'fr', true, $ownerId)->ownerId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notOwnerIds(): iterable
    {
        yield 'empty' => [''];
        yield 'too long' => [str_repeat('a', 256)];
        yield 'a leading space' => [' news:42'];
        yield 'a trailing space' => ['news:42 '];
        yield 'a new line' => ["news:\n42"];
        yield 'a null byte' => ["news:\x0042"];
        yield 'a unit separator' => ["news:\x1F42"];
        yield 'delete' => ["news:\x7F42"];
        yield 'a tab' => ["news:\t42"];
    }

    #[DataProvider('notOwnerIds')]
    public function testItRefusesAMalformedOwnerIdentifier(string $ownerId): void
    {
        $this->expectException(InvalidPageEvent::class);
        $this->expectExceptionMessageIs(\sprintf('"%s" is not an owner identifier: 1 to 255 bytes, without a control character or surrounding spaces.', mb_substr($ownerId, 0, 40)));

        new PagePublished(Url::fromString('https://example.org/'), 'fr', true, $ownerId);
    }

    public function testARetiredPageRefusesAMalformedOwnerIdentifier(): void
    {
        $this->expectException(InvalidPageEvent::class);

        new PageRetired(Url::fromString('https://example.org/'), 'fr', true, '');
    }

    public function testTheMessageCutsALongOwnerIdentifier(): void
    {
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('b', 40).'" is not an owner identifier');

        new PagePublished(Url::fromString('https://example.org/'), 'fr', true, str_repeat('b', 300));
    }

    public function testTheMessageCutsALongOwnerIdentifierByCharacters(): void
    {
        $this->expectException(InvalidPageEvent::class);
        $this->expectExceptionMessageIs('"'.str_repeat('é', 40).'" is not an owner identifier: 1 to 255 bytes, without a control character or surrounding spaces.');

        new PagePublished(Url::fromString('https://example.org/'), 'fr', true, str_repeat('é', 300));
    }
}
