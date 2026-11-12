<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\ValueObject;

use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentReference::class)]
final class ContentReferenceTest extends TestCase
{
    public function testAReferenceNamesItsKindAndItsIdentifier(): void
    {
        $reference = ContentReference::of('static_page', '0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b');

        self::assertSame('static_page', $reference->resourceKey);
        self::assertSame('0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b', $reference->resourceId);
        self::assertSame('static_page:0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b', $reference->toString());
        self::assertSame('static_page:0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b', (string) $reference);
    }

    public function testTheStringFormIsReadBack(): void
    {
        $reference = ContentReference::fromString('news:42');

        self::assertSame('news', $reference->resourceKey);
        self::assertSame('42', $reference->resourceId);
    }

    #[DataProvider('malformedStrings')]
    public function testAStringWithoutExactlyOneColonIsRefused(string $value): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a content reference of the form "resource_key:identifier".', $value));

        ContentReference::fromString($value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedStrings(): iterable
    {
        yield 'no colon' => ['news'];
        yield 'two colons' => ['news:42:43'];
    }

    public function testTheStringFormIsCheckedLikeTheParts(): void
    {
        $this->expectException(InvalidContent::class);

        ContentReference::fromString('News:42');
    }

    #[DataProvider('validResourceKeys')]
    public function testAValidResourceKeyIsKept(string $key): void
    {
        self::assertTrue(ContentReference::isResourceKey($key));
        self::assertSame($key, ContentReference::of($key, '1')->resourceKey);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validResourceKeys(): iterable
    {
        yield 'one letter' => ['a'];
        yield 'snake case with digits' => ['news_2026'];
        yield 'sixty-four characters' => ['a'.str_repeat('b', 63)];
    }

    #[DataProvider('invalidResourceKeys')]
    public function testAnInvalidResourceKeyIsRefused(string $key): void
    {
        self::assertFalse(ContentReference::isResourceKey($key));
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a resource key: a lowercase letter, then at most 63 lowercase letters, digits or underscores.', $key));

        ContentReference::of($key, '1');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidResourceKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['News'];
        yield 'leading digit' => ['1news'];
        yield 'hyphen' => ['static-page'];
        yield 'sixty-five characters' => ['a'.str_repeat('b', 64)];
        yield 'trailing line break' => ["news\n"];
    }

    #[DataProvider('validResourceIds')]
    public function testAValidIdentifierIsKept(string $id): void
    {
        self::assertSame($id, ContentReference::of('news', $id)->resourceId);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function validResourceIds(): iterable
    {
        yield 'integer' => ['7'];
        yield 'uuid' => ['0192f3c4-5d6e-7f80-9a1b-2c3d4e5f6a7b'];
        yield 'ulid with underscore' => ['01J_ABC'];
        yield 'sixty-four characters' => [str_repeat('A', 64)];
    }

    #[DataProvider('invalidResourceIds')]
    public function testAnInvalidIdentifierIsRefused(string $id): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a resource identifier: 1 to 64 letters, digits, hyphens or underscores.', $id));

        ContentReference::of('news', $id);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidResourceIds(): iterable
    {
        yield 'empty' => [''];
        yield 'space' => ['4 2'];
        yield 'colon' => ['a:b'];
        yield 'sixty-five characters' => [str_repeat('A', 65)];
        yield 'trailing line break' => ["42\n"];
    }

    public function testTwoReferencesAreEqualWhenBothPartsAre(): void
    {
        $reference = ContentReference::of('news', '42');

        self::assertTrue($reference->equals(ContentReference::of('news', '42')));
        self::assertFalse($reference->equals(ContentReference::of('news', '43')));
        self::assertFalse($reference->equals(ContentReference::of('page', '42')));
    }
}
