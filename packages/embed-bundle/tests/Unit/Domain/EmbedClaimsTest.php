<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Domain;

use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\EmbedClaims;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbedClaims::class)]
final class EmbedClaimsTest extends TestCase
{
    public function testClaims(): void
    {
        $claims = EmbedClaims::of('calendar', Origin::fromString('https://partner.example'), 'dark', 'event:42', 100, 200);

        self::assertSame(['calendar', 'https://partner.example', 'dark', 'event:42', 100, 200], [$claims->embed, $claims->origin->toString(), $claims->theme, $claims->subject, $claims->issuedAt, $claims->expiresAt]);
        self::assertNull(EmbedClaims::of('a', Origin::fromString('https://p.example'), null, null, 0)->expiresAt);
        self::assertSame(1, EmbedClaims::of('a', Origin::fromString('https://p.example'), null, null, 0, 1)->expiresAt);
        self::assertSame(200, \strlen((string) EmbedClaims::of('a', Origin::fromString('https://p.example'), null, str_repeat('s', 200), 0)->subject));
        self::assertSame(64, \strlen(EmbedClaims::of('a'.str_repeat('b', 63), Origin::fromString('https://p.example'), 'a'.str_repeat('b', 31), null, 0)->embed));
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, int, ?int, string}>
     */
    public static function refused(): iterable
    {
        $name = 'is not the name of an embed: a lowercase letter, then up to 63 lowercase letters, digits, "_" or "-".';
        $theme = 'is not the name of a theme: a lowercase letter, then up to 31 lowercase letters, digits or "-".';
        $subject = 'The subject of an embed is 1 to 200 bytes without control characters.';

        yield 'an uppercase name' => ['Calendar', null, null, 0, null, '"Calendar" '.$name];
        yield 'a name starting with a digit' => ['1cal', null, null, 0, null, '"1cal" '.$name];
        yield 'a long name' => ['a'.str_repeat('b', 64), null, null, 0, null, '"a'.str_repeat('b', 64).'" '.$name];
        yield 'an empty name' => ['', null, null, 0, null, '"" '.$name];
        yield 'a name with a new line' => ["cal\n", null, null, 0, null, "\"cal\n\" ".$name];
        yield 'an uppercase theme' => ['cal', 'Dark', null, 0, null, '"Dark" '.$theme];
        yield 'a long theme' => ['cal', 'a'.str_repeat('b', 32), null, 0, null, '"a'.str_repeat('b', 32).'" '.$theme];
        yield 'an underscore in a theme' => ['cal', 'dark_mode', null, 0, null, '"dark_mode" '.$theme];
        yield 'an empty subject' => ['cal', null, '', 0, null, $subject];
        yield 'a long subject' => ['cal', null, str_repeat('s', 201), 0, null, $subject];
        yield 'a control in the subject' => ['cal', null, "a\x00", 0, null, $subject];
        yield 'a delete in the subject' => ['cal', null, "a\x7f", 0, null, $subject];
        yield 'issued before 1970' => ['cal', null, null, -1, null, 'An embed token expires after it is issued.'];
        yield 'expiring when issued' => ['cal', null, null, 10, 10, 'An embed token expires after it is issued.'];
        yield 'expiring before' => ['cal', null, null, 10, 9, 'An embed token expires after it is issued.'];
    }

    #[DataProvider('refused')]
    public function testRefusedClaims(string $embed, ?string $theme, ?string $subject, int $issued, ?int $expires, string $message): void
    {
        $this->expectExceptionObject(InvalidEmbed::because($message));

        EmbedClaims::of($embed, Origin::fromString('https://p.example'), $theme, $subject, $issued, $expires);
    }
}
