<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Draft;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\Column;
use Phil\ContentBundle\Domain\Draft\WorkingCopy;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\LocaleDimensions\Locale;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(WorkingCopy::class)]
final class WorkingCopyTest extends TestCase
{
    public function testAnAutosaveKeepsItsAuthorAndItsData(): void
    {
        $at = new DateTimeImmutable('2026-11-11 12:00 UTC');
        $copy = WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', ['excerptTitle' => 'A'], $at);

        self::assertTrue($copy->content()->equals(ContentReference::of('news', '42')));
        self::assertSame('fr', $copy->locale()->value);
        self::assertSame('alice', $copy->author());
        self::assertNull($copy->name());
        self::assertTrue($copy->isAutosave());
        self::assertSame(['excerptTitle' => 'A'], $copy->data());
        self::assertSame($at, $copy->savedAt());
        self::assertNotSame(WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', [], $at)->id()->toString(), $copy->id()->toString());

        $later = new DateTimeImmutable('2026-11-11 12:05 UTC');
        $copy->replace(['excerptTitle' => 'B'], 'bob', $later);

        self::assertSame(['excerptTitle' => 'B'], $copy->data());
        self::assertSame('bob', $copy->author());
        self::assertSame($later, $copy->savedAt());
    }

    /**
     * A working copy holds the raw HTML permission of its author once told; saving it again forgets it, since the
     * author may have changed.
     */
    public function testTheRawHtmlPermissionOfItsAuthorIsForgottenWhenItIsSavedAgain(): void
    {
        $copy = WorkingCopy::named(ContentReference::of('news', '42'), Locale::fromString('fr'), 'Court', 'alice', [], new DateTimeImmutable());
        self::assertFalse($copy->rawHtml());

        $copy->grantRawHtml();
        self::assertTrue($copy->rawHtml());

        $copy->replace([], 'bob', new DateTimeImmutable());
        self::assertFalse($copy->rawHtml());
    }

    public function testANamedDraftKeepsItsTrimmedName(): void
    {
        $copy = WorkingCopy::named(ContentReference::of('news', '42'), Locale::fromString('fr'), '  Court ', 'alice', [], new DateTimeImmutable());

        self::assertSame('Court', $copy->name());
        self::assertFalse($copy->isAutosave());
        self::assertSame(str_repeat('é', 100), WorkingCopy::named(ContentReference::of('news', '42'), Locale::fromString('fr'), str_repeat('é', 100), str_repeat('a', 255), [], new DateTimeImmutable())->name());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedNames(): iterable
    {
        yield 'empty' => ['', 'alice'];
        yield 'blank' => ['   ', 'alice'];
        yield 'too long' => [str_repeat('é', 101), 'alice'];
        yield 'a new line' => ["a\nb", 'alice'];
        yield 'delete' => ["a\x7Fb", 'alice'];
    }

    #[DataProvider('refusedNames')]
    public function testANameIsRefused(string $name, string $author): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('The name of a draft is 1 to 100 characters, without a control character.');

        WorkingCopy::named(ContentReference::of('news', '42'), Locale::fromString('fr'), $name, $author, [], new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedAuthors(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [' '];
        yield 'too long' => [str_repeat('é', 256)];
    }

    #[DataProvider('refusedAuthors')]
    public function testAnAuthorIsRefused(string $author): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('The author of a working copy is 1 to 255 characters.');

        WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), $author, [], new DateTimeImmutable());
    }

    public function testTheDataIsBounded(): void
    {
        $atLimit = ['b' => str_repeat('x', WorkingCopy::MAX_BYTES - 8)];
        self::assertSame(WorkingCopy::MAX_BYTES, \strlen((string) json_encode($atLimit)));
        WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', $atLimit, new DateTimeImmutable());

        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIs('The data of a working copy is 262144 bytes of JSON at most; this one is 262145.');

        WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', ['b' => str_repeat('x', WorkingCopy::MAX_BYTES - 7)], new DateTimeImmutable());
    }

    public function testDataThatIsNotJsonIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The data of a working copy is not JSON: ');

        WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', ['b' => "\xB1\x31"], new DateTimeImmutable());
    }

    public function testTheAuthorIsCountedInCharactersNotBytes(): void
    {
        $author = str_repeat('é', WorkingCopy::MAX_AUTHOR_LENGTH);

        self::assertSame($author, WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), $author, [], new DateTimeImmutable())->author());
    }

    public function testTheDataKeepsEveryKey(): void
    {
        $data = ['excerptTitle' => 'A', 'slug' => 'a', 'templateKey' => 'news'];
        $copy = WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', $data, new DateTimeImmutable());

        self::assertSame($data, $copy->data());
    }

    public function testTheMessageOfDataThatIsNotJsonStartsWithItsCause(): void
    {
        try {
            WorkingCopy::autosave(ContentReference::of('news', '42'), Locale::fromString('fr'), 'alice', ['b' => "\xB1\x31"], new DateTimeImmutable());
            self::fail('The data is refused.');
        } catch (InvalidContent $invalidContent) {
            self::assertStringStartsWith('The data of a working copy is not JSON: ', $invalidContent->getMessage());
            self::assertGreaterThan(\strlen('The data of a working copy is not JSON: '), \strlen($invalidContent->getMessage()));
        }
    }

    public function testTheColumnsAreAsWideAsTheLimits(): void
    {
        $author = new ReflectionProperty(WorkingCopy::class, 'author')->getAttributes(Column::class)[0]->newInstance();
        $name = new ReflectionProperty(WorkingCopy::class, 'name')->getAttributes(Column::class)[0]->newInstance();

        self::assertSame([WorkingCopy::MAX_AUTHOR_LENGTH, true], [$author->length, $author->nullable]);
        self::assertSame([WorkingCopy::MAX_NAME_LENGTH, true], [$name->length, $name->nullable]);
    }
}
