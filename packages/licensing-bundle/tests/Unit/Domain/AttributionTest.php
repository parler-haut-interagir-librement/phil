<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Tests\Unit\Domain;

use Phil\LicensingBundle\Domain\Catalog\ResourceKind;
use Phil\LicensingBundle\Domain\Exception\InvalidLicensing;
use Phil\LicensingBundle\Domain\ValueObject\Attribution;
use Phil\LicensingBundle\Domain\ValueObject\SubjectReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Attribution::class)]
#[CoversClass(SubjectReference::class)]
final class AttributionTest extends TestCase
{
    public function testAnAttributionAndItsStoredForm(): void
    {
        $attribution = Attribution::of(ResourceKind::Image, ' Cover ', 'cc-by-4.0', ' Jane ', 'https://example.com/a', " © Jane\n", ' Cropped ');
        $stored = ['kind' => 'image', 'title' => 'Cover', 'license' => 'cc-by-4.0', 'author' => 'Jane', 'url' => 'https://example.com/a', 'notice' => '© Jane', 'changes' => 'Cropped'];

        self::assertSame($stored, $attribution->toArray());
        self::assertEquals($attribution, Attribution::fromArray($stored));
        self::assertTrue($attribution->credits());

        $bare = Attribution::of(ResourceKind::Font, 'Serif', 'cc0-1.0');
        self::assertSame(['kind' => 'font', 'title' => 'Serif', 'license' => 'cc0-1.0', 'author' => null, 'url' => null, 'notice' => null, 'changes' => null], $bare->toArray());
        self::assertEquals($bare, Attribution::fromArray(['kind' => 'font', 'title' => 'Serif', 'license' => 'cc0-1.0']));
        self::assertFalse($bare->credits());
        self::assertTrue(Attribution::of(ResourceKind::Text, 'SRD', 'orc', author: 'Example')->credits());
        self::assertTrue(Attribution::of(ResourceKind::Text, 'SRD', 'orc', notice: 'Credit')->credits());
    }

    public function testTheLimits(): void
    {
        $attribution = Attribution::of(ResourceKind::Other, str_repeat('t', 255), 'orc', str_repeat('a', 255), null, str_repeat('n', 4000), str_repeat('c', 1000));

        self::assertSame(255, mb_strlen($attribution->title));
        self::assertSame(255, mb_strlen((string) $attribution->author));
        self::assertSame(4000, mb_strlen((string) $attribution->notice));
        self::assertSame(1000, mb_strlen((string) $attribution->changes));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'no title' => [['kind' => 'text', 'title' => ' ', 'license' => 'orc'], 'The resource title is required.'];
        yield 'long title' => [['kind' => 'text', 'title' => str_repeat('t', 256), 'license' => 'orc'], 'The resource title holds 255 characters at most.'];
        yield 'bad license' => [['kind' => 'text', 'title' => 'T', 'license' => 'ORC'], 'The license code "ORC" is 1 to 64 lowercase letters, digits, dots and hyphens.'];
        yield 'long author' => [['kind' => 'text', 'title' => 'T', 'license' => 'orc', 'author' => str_repeat('a', 256)], 'The resource author holds 255 characters at most.'];
        yield 'bad address' => [['kind' => 'text', 'title' => 'T', 'license' => 'orc', 'url' => 'ftp://example.com'], 'The resource address is an absolute http or https address.'];
        yield 'long notice' => [['kind' => 'text', 'title' => 'T', 'license' => 'orc', 'notice' => str_repeat('n', 4001)], 'The resource notice holds 4000 characters at most.'];
        yield 'long changes' => [['kind' => 'text', 'title' => 'T', 'license' => 'orc', 'changes' => str_repeat('c', 1001)], 'The resource changes holds 1000 characters at most.'];
        yield 'unknown kind' => [['kind' => 'sound', 'title' => 'T', 'license' => 'orc'], 'The resource kind is unknown.'];
        yield 'no kind' => [['title' => 'T', 'license' => 'orc'], 'The resource kind is unknown.'];
        yield 'no title key' => [['kind' => 'text', 'license' => 'orc'], 'The resource title is required.'];
        yield 'no license key' => [['kind' => 'text', 'title' => 'T'], 'The license code "" is 1 to 64 lowercase letters, digits, dots and hyphens.'];
        yield 'not a text' => [['kind' => 'text', 'title' => 'T', 'license' => 'orc', 'author' => 12], 'The "author" of an attribution is a text.'];
    }

    /**
     * @param array<string, mixed> $attribution
     */
    #[DataProvider('refused')]
    public function testAnAttributionIsChecked(array $attribution, string $message): void
    {
        $this->expectExceptionObject(InvalidLicensing::because($message));

        Attribution::fromArray($attribution);
    }

    public function testASubject(): void
    {
        $subject = SubjectReference::of('App\Entity\Book', 42);

        self::assertSame('App\Entity\Book', $subject->type);
        self::assertSame('42', $subject->id);
        self::assertTrue($subject->equals(SubjectReference::of('App\Entity\Book', '42')));
        self::assertFalse($subject->equals(SubjectReference::of('App\Entity\Book', '43')));
        self::assertFalse($subject->equals(SubjectReference::of('book', '42')));
        self::assertSame(str_repeat('t', 128), SubjectReference::of(str_repeat('t', 128), str_repeat('i', 64))->type);
        self::assertSame('a:b.c_d-e', SubjectReference::of('a:b.c_d-e', '!~')->type);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function refusedSubjects(): iterable
    {
        $type = 'The subject type "%s" is 1 to 128 letters, digits, "_", ".", ":", "\\" or "-".';
        $id = 'The subject identifier is 1 to 64 printable ASCII characters.';

        yield 'empty type' => ['', '1', \sprintf($type, '')];
        yield 'space in type' => ['a b', '1', \sprintf($type, 'a b')];
        yield 'long type' => [str_repeat('t', 129), '1', \sprintf($type, str_repeat('t', 129))];
        yield 'type with a line' => ["book\n", '1', \sprintf($type, "book\n")];
        yield 'empty id' => ['book', '', $id];
        yield 'space in id' => ['book', 'a b', $id];
        yield 'delete in id' => ['book', "a\x7F", $id];
        yield 'long id' => ['book', str_repeat('i', 65), $id];
        yield 'id with a line' => ['book', "1\n", $id];
    }

    #[DataProvider('refusedSubjects')]
    public function testASubjectIsChecked(string $type, string $id, string $message): void
    {
        $this->expectExceptionObject(InvalidLicensing::because($message));

        SubjectReference::of($type, $id);
    }
}
