<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Tests\Unit\Domain;

use Phil\FormsAutosave\Domain\Exception\AutosaveException;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(DraftKey::class)]
#[CoversClass(InvalidDraft::class)]
final class DraftKeyTest extends TestCase
{
    #[TestWith(['abcdefghijklmnop'])]
    #[TestWith(['ABCDEFGHIJKLMNOPQRSTUVWXYZ-_0123456789abcdefghijklmnopqrstuvwxyz'])]
    public function testAKeyIsSixteenToSixtyFourUrlSafeCharacters(string $value): void
    {
        self::assertSame($value, DraftKey::of($value)->value);
    }

    #[TestWith([''])]
    #[TestWith(['abcdefghijklmno'])]
    #[TestWith(['ABCDEFGHIJKLMNOPQRSTUVWXYZ-_0123456789abcdefghijklmnopqrstuvwxyz0'])]
    #[TestWith(['abcdefghijklmnop/'])]
    #[TestWith(['abcdefghijklmnop.'])]
    #[TestWith(["abcdefghijklmnop\n"])]
    #[TestWith(['éabcdefghijklmnop'])]
    public function testAnythingElseIsRefused(string $value): void
    {
        try {
            DraftKey::of($value);
            self::fail($value);
        } catch (InvalidDraft $invalidDraft) {
            self::assertInstanceOf(AutosaveException::class, $invalidDraft);
            self::assertSame('A draft key is 16 to 64 letters, digits, "-" or "_".', $invalidDraft->getMessage());
        }
    }
}
