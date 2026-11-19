<?php

declare(strict_types=1);

namespace Phil\Avatar\Tests\Unit;

use DOMDocument;
use Phil\Avatar\AvatarGenerator;
use Phil\Avatar\AvatarOptions;
use Phil\Avatar\Exception\InvalidAvatar;
use Phil\Avatar\Palette;
use Phil\Avatar\Seed;
use Phil\Avatar\Shape;
use Phil\Avatar\Tests\Unit\Fixture\StarStyle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AvatarGenerator::class)]
#[CoversClass(AvatarOptions::class)]
final class AvatarGeneratorTest extends TestCase
{
    private const string FONT = 'system-ui, -apple-system, &apos;Segoe UI&apos;, Roboto, &apos;Noto Sans&apos;, &apos;Noto Sans CJK&apos;, &apos;Noto Sans Arabic&apos;, sans-serif';

    public function testAnAvatarIsTheSameForTheSameSeed(): void
    {
        $svg = new AvatarGenerator()->svg('member-7', new AvatarOptions(size: 48, name: 'Ada Lovelace'));

        self::assertSame(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="48" height="48" role="img" aria-label="Ada Lovelace"><title>Ada Lovelace</title>'
            .'<defs><clipPath id="phil-avatar-186fe78d594cd3b8"><circle cx="50" cy="50" r="50"/></clipPath></defs>'
            .'<g clip-path="url(#phil-avatar-186fe78d594cd3b8)"><rect width="100" height="100" fill="#f4a6a6"/>'
            .'<text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-family="'.self::FONT.'" font-size="40" font-weight="600" fill="#111111">AL</text></g></svg>',
            $svg,
        );
        self::assertSame($svg, new AvatarGenerator()->svg(new Seed('member-7'), new AvatarOptions(size: 48, name: 'Ada Lovelace')));
        self::assertNotSame($svg, new AvatarGenerator()->svg('member-8', new AvatarOptions(size: 48, name: 'Ada Lovelace')));
    }

    public function testTheOutlines(): void
    {
        $generator = new AvatarGenerator();

        $square = $generator->svg('member-7', new AvatarOptions(style: 'pixels', shape: Shape::Square));
        self::assertStringNotContainsString('clip', $square);
        self::assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="64" height="64" aria-hidden="true" focusable="false"><rect width="100" height="100" fill="#', $square);

        $rounded = $generator->svg('member-7', new AvatarOptions(style: 'pixels', shape: Shape::Rounded));
        self::assertMatchesRegularExpression('~^<svg [^>]+><defs><clipPath id="(phil-avatar-[0-9a-f]{16})"><rect width="100" height="100" rx="16"/></clipPath></defs><g clip-path="url\(#\1\)"><rect width="100".*</g></svg>$~', $rounded);
        self::assertStringContainsString(substr($square, \strlen('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" width="64" height="64" aria-hidden="true" focusable="false">'), -6), $rounded);

        $circle = $generator->svg('member-7', new AvatarOptions(style: 'pixels'));
        self::assertStringContainsString('<circle cx="50" cy="50" r="50"/></clipPath>', $circle);
    }

    public function testTheClipIsNamedByTheSeedStyleAndShape(): void
    {
        $id = static function (string $svg): string {
            preg_match('/clipPath id="([^"]+)"/', $svg, $match);

            return $match[1] ?? '';
        };
        $generator = new AvatarGenerator();
        $base = $id($generator->svg('member-7', new AvatarOptions(style: 'shapes')));

        self::assertSame('phil-avatar-'.substr(hash('sha256', "member-7\0shapes\0circle"), 0, 16), $base);
        self::assertSame($base, $id($generator->svg('member-7', new AvatarOptions(style: 'shapes', size: 32, palette: Palette::named('vivid')))));
        self::assertNotSame($base, $id($generator->svg('member-8', new AvatarOptions(style: 'shapes'))));
        self::assertNotSame($base, $id($generator->svg('member-7', new AvatarOptions(style: 'pixels'))));
        self::assertNotSame($base, $id($generator->svg('member-7', new AvatarOptions(style: 'shapes', shape: Shape::Rounded))));
    }

    public function testAnAvatarWithoutANameIsDecorative(): void
    {
        $svg = new AvatarGenerator()->svg('member-7');

        self::assertStringContainsString(' aria-hidden="true" focusable="false">', $svg);
        self::assertStringNotContainsString('role=', $svg);
        self::assertStringNotContainsString('<title>', $svg);
        self::assertStringNotContainsString('<text', $svg);
    }

    public function testTheLabelNamesTheAvatar(): void
    {
        $svg = new AvatarGenerator()->svg('member-7', new AvatarOptions(name: 'Ada Lovelace', label: ' Avatar of Ada '));

        self::assertStringContainsString(' role="img" aria-label="Avatar of Ada"><title>Avatar of Ada</title>', $svg);
        self::assertStringContainsString('>AL</text>', $svg);
        self::assertStringNotContainsString('aria-hidden', $svg);
    }

    public function testNoTextOfTheCallerReachesTheMarkupUnescaped(): void
    {
        $hostile = '"><script>alert(1)</script><x a=\'';
        $svg = new AvatarGenerator()->svg('member-7', new AvatarOptions(name: $hostile.' Zed', label: $hostile));

        self::assertStringNotContainsString('<script', $svg);
        self::assertStringNotContainsString('<x ', $svg);

        $document = $this->parse($svg);
        self::assertSame('svg', $document->documentElement?->nodeName);
        self::assertSame($hostile, $document->documentElement->getAttribute('aria-label'));
        self::assertSame($hostile, $document->getElementsByTagName('title')->item(0)?->textContent);
        self::assertSame(0, $document->getElementsByTagName('script')->length);
    }

    public function testEveryAvatarIsWellFormed(): void
    {
        $generator = new AvatarGenerator();

        foreach (['initials', 'shapes', 'pixels'] as $style) {
            foreach (Shape::cases() as $shape) {
                foreach (range(1, 10) as $seed) {
                    self::assertSame('svg', $this->parse($generator->svg('m-'.$seed, new AvatarOptions(style: $style, shape: $shape, name: 'Ada Lovelace')))->documentElement?->nodeName);
                }
            }
        }
    }

    public function testAnInvalidNameDrawsShapesUnderAScrubbedLabel(): void
    {
        $svg = new AvatarGenerator()->svg('member-7', new AvatarOptions(name: "Ada \xC3\x28"));

        self::assertStringNotContainsString('<text', $svg);
        self::assertStringContainsString('aria-label="Ada ?("', $svg);
    }

    public function testADataUriHoldsTheSvg(): void
    {
        $generator = new AvatarGenerator();
        $options = new AvatarOptions(name: 'Ada');
        $uri = $generator->dataUri('member-7', $options);

        self::assertStringStartsWith('data:image/svg+xml,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22', $uri);
        self::assertSame($generator->svg('member-7', $options), rawurldecode(substr($uri, 19)));
        self::assertSame($generator->svg(new Seed('member-7')), rawurldecode(substr($generator->dataUri(new Seed('member-7')), 19)));
    }

    public function testAnApplicationAddsOrReplacesAStyle(): void
    {
        $generator = new AvatarGenerator([new StarStyle()]);

        self::assertSame(['initials', 'shapes', 'pixels', 'star'], $generator->styles());
        self::assertStringContainsString('<circle cx="50" cy="50" r="', $generator->svg('member-7', new AvatarOptions(style: 'star', shape: Shape::Square)));

        $replaced = new AvatarGenerator([new StarStyle('initials')]);
        self::assertSame(['initials', 'shapes', 'pixels'], $replaced->styles());
        self::assertStringNotContainsString('<text', $replaced->svg('member-7', new AvatarOptions(name: 'Ada')));
        self::assertSame(['initials', 'shapes', 'pixels', 'a-1', str_repeat('s', 32)], new AvatarGenerator([new StarStyle('a-1'), new StarStyle(str_repeat('s', 32))])->styles());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidStyleNames(): iterable
    {
        yield 'empty' => [''];
        yield 'capitals' => ['Star'];
        yield 'a space' => ['my star'];
        yield 'too long' => [str_repeat('s', 33)];
        yield 'a new line after' => ["star\n"];
    }

    #[DataProvider('invalidStyleNames')]
    public function testAStyleNameIsChecked(string $name): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('The name of a style is 1 to 32 lowercase letters, digits or "-".'));

        new AvatarGenerator([new StarStyle($name)]);
    }

    public function testAnUnknownStyleIsRefused(): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('The styles are initials, shapes, pixels.'));

        new AvatarGenerator()->svg('member-7', new AvatarOptions(style: 'robots'));
    }

    public function testAnEmailAddressIsNeverASeed(): void
    {
        $this->expectExceptionObject(InvalidAvatar::because('A seed is an opaque identifier, never an email address.'));

        new AvatarGenerator()->svg('ada@example.com');
    }

    public function testTheDefaultOptions(): void
    {
        $options = new AvatarOptions();

        self::assertSame('initials', $options->style);
        self::assertSame(64, $options->size);
        self::assertSame(Shape::Circle, $options->shape);
        self::assertEquals(Palette::named('default'), $options->palette);
        self::assertNull($options->name);
        self::assertNull($options->label);
        self::assertSame(2, $options->maxLetters);
        self::assertNull($options->accessibleName());
    }

    public function testTheAccessibleName(): void
    {
        self::assertSame('Ada', new AvatarOptions(name: ' Ada ')->accessibleName());
        self::assertSame('Ada', new AvatarOptions(name: 'Ada', label: '   ')->accessibleName());
        self::assertNull(new AvatarOptions(name: ' ', label: '')->accessibleName());
        self::assertSame(200, mb_strlen((string) new AvatarOptions(name: str_repeat('é', 300))->accessibleName()));
        self::assertSame(200, mb_strlen((string) new AvatarOptions(label: str_repeat('é', 200))->accessibleName()));
    }

    public function testTheBoundsOfTheOptions(): void
    {
        self::assertSame(16, new AvatarOptions(size: 16)->size);
        self::assertSame(1024, new AvatarOptions(size: 1024)->size);
        self::assertSame(1, new AvatarOptions(maxLetters: 1)->maxLetters);
        self::assertSame(3, new AvatarOptions(maxLetters: 3)->maxLetters);
        self::assertStringContainsString('>AKL</text>', new AvatarGenerator()->svg('m', new AvatarOptions(name: 'Ada King Lovelace', maxLetters: 3)));
    }

    /**
     * @return iterable<string, array{array{size?: int, label?: string, maxLetters?: int}, string}>
     */
    public static function invalidOptions(): iterable
    {
        $size = 'An avatar is 16 to 1024 pixels wide.';
        $label = 'The label of an avatar is valid UTF-8, 200 characters at most.';

        yield 'too small' => [['size' => 15], $size];
        yield 'too large' => [['size' => 1025], $size];
        yield 'a label too long' => [['label' => str_repeat('é', 201)], $label];
        yield 'a label not UTF-8' => [['label' => "\xC3\x28"], $label];
        yield 'no letters' => [['maxLetters' => 0], 'Initials hold 1 to 3 letters.'];
        yield 'four letters' => [['maxLetters' => 4], 'Initials hold 1 to 3 letters.'];
    }

    /**
     * @param array{size?: int, label?: string, maxLetters?: int} $options
     */
    #[DataProvider('invalidOptions')]
    public function testOptionsOutOfBoundsAreRefused(array $options, string $message): void
    {
        $this->expectExceptionObject(InvalidAvatar::because($message));

        new AvatarOptions(...$options);
    }

    private function parse(string $svg): DOMDocument
    {
        self::assertNotSame('', $svg);
        $document = new DOMDocument();
        self::assertTrue($document->loadXML($svg));

        return $document;
    }
}
