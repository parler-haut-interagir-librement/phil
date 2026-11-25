<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use Phil\UxKit\Application\CelebrationStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CelebrationStream::class)]
final class CelebrationStreamTest extends TestCase
{
    public function testTheStreamOfAnEffectWithItsMessageAndItsFocus(): void
    {
        self::assertSame(
            '<turbo-stream action="celebrate" effect="lasers" message="You won" focus="#winner"><template></template></turbo-stream>',
            new CelebrationStream()->render('You won', 'lasers', '#winner'),
        );
    }

    public function testWithoutAMessageNorAFocusTheyAreLeftOut(): void
    {
        self::assertSame('<turbo-stream action="celebrate" effect="confetti"><template></template></turbo-stream>', new CelebrationStream()->render());
        self::assertSame('<turbo-stream action="celebrate" effect="confetti"><template></template></turbo-stream>', new CelebrationStream()->render('', 'confetti', ''));
    }

    public function testEveryValueIsEscapedAsAnAttribute(): void
    {
        self::assertSame(
            '<turbo-stream action="celebrate" effect="confetti" message="&quot;&gt;&lt;script&gt;&apos;&amp;" focus="[data-id=&quot;7&quot;]"><template></template></turbo-stream>',
            new CelebrationStream()->render("\"><script>'&", 'confetti', '[data-id="7"]'),
        );
        self::assertStringContainsString("message=\"\u{FFFD}\"", new CelebrationStream()->render("\xFF"), 'invalid UTF-8 replaced');
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function effects(): iterable
    {
        foreach (CelebrationStream::EFFECTS as $effect) {
            yield $effect => [$effect, $effect];
        }

        yield 'unknown' => ['disco', 'confetti'];
        yield 'case' => ['Lasers', 'confetti'];
        yield 'empty' => ['', 'confetti'];
    }

    #[DataProvider('effects')]
    public function testAnEffectOfTheListOrConfetti(string $asked, string $drawn): void
    {
        self::assertSame($drawn, CelebrationStream::effect($asked));
        self::assertStringContainsString(\sprintf(' effect="%s"', $drawn), new CelebrationStream()->render(null, $asked));
    }

    public function testTheEffectsAreThoseOfTheController(): void
    {
        $controller = (string) file_get_contents(__DIR__.'/../../celebration/assets/controllers/celebration_effects.js');
        self::assertSame(1, preg_match('/export const EFFECTS = \[([^\]]+)\];/', $controller, $match));
        preg_match_all("/'([a-z-]+)'/", $match[1], $names);

        self::assertSame($names[1], CelebrationStream::EFFECTS);
    }
}
