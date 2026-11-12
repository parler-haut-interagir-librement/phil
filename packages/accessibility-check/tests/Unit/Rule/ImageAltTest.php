<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Rule;

use Phil\AccessibilityCheck\Rule\FileName;
use Phil\AccessibilityCheck\Rule\FragmentRule;
use Phil\AccessibilityCheck\Rule\ImageAlt;
use Phil\AccessibilityCheck\Severity;
use Phil\AccessibilityCheck\SuccessCriterion;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(ImageAlt::class)]
#[CoversClass(FileName::class)]
#[CoversClass(FragmentRule::class)]
final class ImageAltTest extends RuleTestCase
{
    public function testTheRuleIsDescribed(): void
    {
        $rule = new ImageAlt();

        self::assertSame(['image-alt', SuccessCriterion::NonTextContent, Severity::Error], [$rule->id(), $rule->criterion(), $rule->severity()]);
    }

    public function testAnImageSaysWhatItShows(): void
    {
        self::assertSame([
            'image-alt.missing @ img:nth-of-type(1) (source=/a.png)',
            'image-alt.blank @ img:nth-of-type(4) (source=d.png) !warning',
            'image-alt.filename @ img:nth-of-type(5) (source=/up/e.jpg?v=2, alt=e) !warning',
            'image-alt.missing @ img:nth-of-type(9) (source=)',
            'image-alt.missing @ img:nth-of-type(12) (source=both.png)',
            'image-alt.missing @ map > area:nth-of-type(1) (source=/zone)',
            'image-alt.missing @ input:nth-of-type(1) (source=go.png)',
            'image-alt.missing @ input:nth-of-type(5) (source=in.png)',
        ], self::html(new ImageAlt(), <<<'HTML'
            <img src="/a.png"><img src="b.png" alt="A cat"><img src="c.png" alt=""><img src="d.png" alt="  "><img src="/up/e.jpg?v=2" alt=" e ">
            <img src="f.png" aria-label="Fish"><img src="g.png" title="Goat"><img src="h.png" role="presentation"><img>
            <img src="i.png" hidden><img src="j.png" role="none">
            <map><area href="/zone"><area alt="Zone" href="/z"><area></map>
            <input type="image" src="go.png"><input type=" IMAGE " alt="Go"><input type="text"><input><input type=" Image " src="in.png"><img src="both.png" href="no">
            HTML));
    }

    public function testAnImageBlockHasAnAlternativeOrACaption(): void
    {
        self::assertSame([
            'image-alt.missing @ blocks[0] (source=/a.png)',
            'image-alt.filename @ blocks[3] (source=/d.png, alt=IMG_2041) !warning',
            'image-alt.missing @ blocks[4] (source=)',
            'image-alt.missing @ blocks[5].data.files[1] (source=/g2.png)',
            'image-alt.filename @ blocks[5].data.files[2] (source=, alt=g3.webp) !warning',
            'image-alt.missing @ blocks[5].data.caption img (source=inline.png)',
        ], self::ujo(new ImageAlt(), [
            ['id' => 'a', 'type' => 'image', 'data' => ['file' => ['url' => '/a.png'], 'caption' => ' ', 'alt' => ' ']],
            ['id' => 'b', 'type' => 'image', 'data' => ['file' => ['url' => '/b.png'], 'caption' => '<b>A</b> cat']],
            ['id' => 'c', 'type' => 'image', 'data' => ['file' => ['url' => '/c.png'], 'caption' => '', 'alt' => 'A dog']],
            ['id' => 'd', 'type' => 'image', 'data' => ['file' => ['url' => '/d.png'], 'caption' => 'IMG_2041']],
            ['id' => 'e', 'type' => 'image', 'data' => ['file' => 'x']],
            ['id' => 'g', 'type' => 'gallery', 'data' => ['caption' => '<img src="inline.png">', 'files' => [['url' => '/g1.png', 'caption' => 'One'], ['url' => '/g2.png'], ['caption' => 'g3.webp']]]],
            ['id' => 'h', 'type' => 'gallery', 'data' => ['files' => 'none']],
            ['id' => 'p', 'type' => 'paragraph', 'data' => ['text' => 'x']],
        ]));
    }

    /**
     * @return iterable<array{string, string, bool}>
     */
    public static function names(): iterable
    {
        yield ['photo.JPG', '', true];
        yield ['my-photo_2.webp', '', true];
        yield ['a photo.jpg', '', false];
        yield ['dir/a.png', '', false];
        yield ['IMG_2041', '', true];
        yield ['DSC0001', '', true];
        yield ['dscn 12', '', true];
        yield ['Screenshot-3', '', true];
        yield ['untitled', '', true];
        yield ['Image of a cat', '', false];
        yield ['cat', '/up/cat.png', true];
        yield ['CAT.PNG', '/up/cat.png?x=1', true];
        yield ['cat', '', false];
        yield ['A cat', '/up/cat.png', false];
        yield ['cat', '/up/', false];
        yield ['png', '/up/cat.png', false];
        yield ['cat', 'https://cdn.example/up/cat.png#top', true];
        yield ['cat', 'up\\cat.png', true];
        yield ['cat', 'https://cdn.example\\up\\cat.png', true];
        yield ['cat', 'https://cat.png@cdn.example/up/dog.png', false];
        yield ['dog', 'https://cat.png@cdn.example/up/dog.png', true];
        yield ['café', '/up/caf%C3%A9.png', true];
        yield ['café', 'https://bücher.example/up/café.png', true];
        yield ['cat', '/up/cat.png/../dog.png', false];
        yield ['cat', '//cdn.example/up/cat.png', true];
        yield ['cat', 'https://', false];
    }

    #[DataProvider('names')]
    public function testAFileNameIsNotADescription(string $alternative, string $source, bool $expected): void
    {
        self::assertSame($expected, FileName::looksLikeOne($alternative, $source));
    }
}
