<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Unit\Domain\ValueObject;

use Phil\CommerceBundle\Domain\Channel\Visibility;
use Phil\CommerceBundle\Domain\ValueObject\DocumentType;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentType::class)]
final class DocumentTypeTest extends TestCase
{
    public function testANumberIsThePrefixAndThePaddedSequence(): void
    {
        $type = DocumentType::of('packing_slip', 'PS-', 6);

        self::assertSame('packing_slip', $type->code);
        self::assertSame('PS-', $type->prefix);
        self::assertSame(6, $type->digits);
        self::assertSame('PS-000042', $type->format(42));
        self::assertSame('PS-1234567', $type->format(1_234_567));
        self::assertSame(8, DocumentType::of('slip', '')->digits);
        self::assertSame(Visibility::Hidden, $type->visibility);
        self::assertSame(Visibility::Unlisted, DocumentType::of('slip', '', 8, Visibility::Unlisted)->visibility);
    }

    public function testTheLongestNumberIsAccepted(): void
    {
        $type = DocumentType::of(str_repeat('a', 16), str_repeat('P', 14), 18);

        self::assertSame(32, \strlen($type->format(1)));
        self::assertSame(1, DocumentType::of('a', '', 1)->digits);
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function refused(): iterable
    {
        yield 'an empty code' => ['', 'P-', 8, '"" is not a document type'];
        yield 'a code too long' => [str_repeat('a', 17), 'P-', 8, 'is not a document type'];
        yield 'a code starting with a digit' => ['1slip', 'P-', 8, 'is not a document type'];
        yield 'an uppercase code' => ['Slip', 'P-', 8, 'is not a document type'];
        yield 'a prefix too long' => ['slip', str_repeat('P', 17), 8, 'holds up to 16 visible ASCII characters'];
        yield 'a prefix with a space' => ['slip', 'P -', 8, 'holds up to 16 visible ASCII characters'];
        yield 'no digit' => ['slip', 'P-', 0, 'pads to 1 to 18 digits'];
        yield 'too many digits' => ['slip', 'P-', 19, 'pads to 1 to 18 digits'];
        yield 'a number too long' => ['slip', str_repeat('P', 15), 18, 'its numbers at most 32 characters long'];
    }

    #[DataProvider('refused')]
    public function testATypeIsRefusedOutsideItsBounds(string $code, string $prefix, int $digits, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        DocumentType::of($code, $prefix, $digits);
    }
}
