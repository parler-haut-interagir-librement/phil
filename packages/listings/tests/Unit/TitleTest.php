<?php

declare(strict_types=1);

namespace Phil\Listings\Tests\Unit;

use Phil\Listings\Exception\InvalidListing;
use Phil\Listings\Title;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

#[CoversClass(Title::class)]
final class TitleTest extends TestCase
{
    #[TestWith(['Vélo de route', 'Vélo de route'])]
    #[TestWith(["  Two\tplayers\n wanted  ", 'Two players wanted'])]
    #[TestWith(["Prix\u{00A0}: 5 €", 'Prix : 5 €'])]
    #[TestWith(["Line\u{2028}break\u{2029}here", 'Line break here'])]
    #[TestWith(['日本語のタイトル', '日本語のタイトル'])]
    public function testATitleIsFolded(string $given, string $kept): void
    {
        self::assertSame($kept, Title::of($given)->value);
    }

    #[TestWith([''])]
    #[TestWith([" \t\n"])]
    #[TestWith(["Zero\u{200B}width"])]
    #[TestWith(["Bell\x07"])]
    #[TestWith(["Nul\x00"])]
    public function testAnEmptyOrControlTitleIsRefused(string $given): void
    {
        $this->expectException(InvalidListing::class);

        Title::of($given);
    }

    public function testAnInvalidEncodingIsRefused(): void
    {
        $this->expectExceptionObject(InvalidListing::because('A title is valid UTF-8 of at most 2048 bytes.'));

        Title::of("Caf\xE9");
    }

    public function testATitleIsAtMostOneHundredTwentyCharacters(): void
    {
        self::assertSame(120, mb_strlen(Title::of(str_repeat('é', 120))->value));

        $this->expectExceptionObject(InvalidListing::because('A title is 1 to 120 characters; this one has 121.'));

        Title::of(str_repeat('é', 121));
    }

    public function testTheInputIsBoundedBeforeFolding(): void
    {
        self::assertSame('a b', Title::of('a'.str_repeat(' ', 2046).'b')->value);

        $this->expectExceptionObject(InvalidListing::because('A title is valid UTF-8 of at most 2048 bytes.'));

        Title::of('a'.str_repeat(' ', 2047).'b');
    }

    public function testAControlCharacterIsNamedAsSuch(): void
    {
        $this->expectExceptionObject(InvalidListing::because('A title holds no control or format character.'));

        Title::of("Soft\u{00AD}hyphen");
    }
}
