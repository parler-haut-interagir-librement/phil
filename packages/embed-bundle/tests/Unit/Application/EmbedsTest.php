<?php

declare(strict_types=1);

namespace Phil\EmbedBundle\Tests\Unit\Application;

use Phil\EmbedBundle\Application\Embed;
use Phil\EmbedBundle\Application\Embeds;
use Phil\EmbedBundle\Domain\Exception\InvalidEmbed;
use Phil\EmbedBundle\Domain\ValueObject\Origin;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Embed::class)]
#[CoversClass(Embeds::class)]
final class EmbedsTest extends TestCase
{
    public function testAnEmbedAllowsItsOriginsAndHasItsThemes(): void
    {
        $embed = Embed::of('calendar', [Origin::fromString('https://a.example'), Origin::fromString('https://b.example')], ['light', 'light', 'dark'], 'dark', 3600);

        self::assertSame(['calendar', ['light', 'dark'], 'dark', 3600], [$embed->name, $embed->themes, $embed->defaultTheme, $embed->ttl]);
        self::assertTrue($embed->allows(Origin::fromString('https://b.example')));
        self::assertFalse($embed->allows(Origin::fromString('https://c.example')));
        self::assertTrue($embed->hasTheme('light'));
        self::assertFalse($embed->hasTheme('blue'));
    }

    public function testAnEmbedWithoutOriginsAllowsAny(): void
    {
        $embed = Embed::of('form');

        self::assertTrue($embed->allows(Origin::fromString('https://anywhere.example')));
        self::assertSame([[], [], null, null], [$embed->origins, $embed->themes, $embed->defaultTheme, $embed->ttl]);
        self::assertSame(1, Embed::of('a', ttl: 1)->ttl);
        self::assertSame(315_360_000, Embed::of('a', ttl: 315_360_000)->ttl);
    }

    /**
     * @return iterable<string, array{string, list<string>, ?string, ?int, string}>
     */
    public static function refused(): iterable
    {
        yield 'a name' => ['Cal', [], null, null, '"Cal" is not the name of an embed: a lowercase letter, then up to 63 lowercase letters, digits, "_" or "-".'];
        yield 'a theme' => ['cal', ['Dark'], null, null, '"Dark" is not the name of a theme: a lowercase letter, then up to 31 lowercase letters, digits or "-".'];
        yield 'a default theme' => ['cal', ['light'], 'dark', null, 'The default theme of the embed "cal" is one of its themes.'];
        yield 'no lifetime' => ['cal', [], null, 0, 'A token of the embed "cal" holds from one second to ten years.'];
        yield 'too long a lifetime' => ['cal', [], null, 315_360_001, 'A token of the embed "cal" holds from one second to ten years.'];
    }

    /**
     * @param list<string> $themes
     */
    #[DataProvider('refused')]
    public function testARefusedEmbed(string $name, array $themes, ?string $default, ?int $ttl, string $message): void
    {
        $this->expectExceptionObject(InvalidEmbed::because($message));

        Embed::of($name, [], $themes, $default, $ttl);
    }

    public function testTheEmbedsByName(): void
    {
        $embeds = new Embeds([Embed::of('calendar'), Embed::of('form')]);

        self::assertSame('form', $embeds->get('form')->name);
        self::assertSame(['calendar', 'form'], $embeds->names());
        self::assertSame([], new Embeds()->names());

        $this->expectExceptionObject(InvalidEmbed::because('No embed is named "map".'));
        $embeds->get('map');
    }

    public function testTwoEmbedsOfOneName(): void
    {
        $this->expectExceptionObject(InvalidEmbed::because('Two embeds are named "form".'));

        new Embeds([Embed::of('form'), Embed::of('form')]);
    }
}
