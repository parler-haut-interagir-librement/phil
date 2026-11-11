<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class IllustrationTest extends KitTestCase
{
    private const string CANDIDATES = '[{src: "/a.png", webp: "/a.webp"}, {src: "/b.png", alt: "A knight", width: 800, height: 600}, "/c.svg"]';

    public function testTheSeedChoosesTheCandidate(): void
    {
        self::assertSame(
            '<figure class="isBleed"><picture><source type="image/webp" srcset="/a.webp"><img src="/a.png" alt="A drawing" loading="lazy" decoding="async"></picture></figure>',
            $this->render("<twig:Illustration :candidates='".self::CANDIDATES.'\' alt="A drawing" :seed="3" class="isBleed" />'),
        );
        self::assertSame(
            '<figure><img src="/b.png" alt="A knight" width="800" height="600" loading="lazy" decoding="async"></figure>',
            $this->render("<twig:Illustration :candidates='".self::CANDIDATES.'\' alt="A drawing" :seed="-4" />'),
        );
        self::assertSame(
            '<figure><img src="/c.svg" alt="A drawing" loading="eager" decoding="async"><figcaption>Soon</figcaption></figure>',
            $this->render("<twig:Illustration :candidates='".self::CANDIDATES.'\' alt="A drawing" :seed="2" caption="Soon" loading="eager" />'),
        );
    }

    public function testWithoutASeedEachCandidateCanBeDrawn(): void
    {
        $seen = [];

        for ($i = 0; $i < 200 && \count($seen) < 3; ++$i) {
            preg_match('/<img src="([^"]+)"/', $this->render("<twig:Illustration :candidates='".self::CANDIDATES.'\' alt="" />'), $match);
            $seen[$match[1] ?? ''] = true;
        }

        ksort($seen);
        self::assertSame(['/a.png', '/b.png', '/c.svg'], array_keys($seen));
    }

    public function testOneUrlAndItsSizes(): void
    {
        self::assertSame(
            '<figure><img src="/only.png" alt="" sizes="50vw" loading="lazy" decoding="async"></figure>',
            $this->render('<twig:Illustration candidates="/only.png" alt="" sizes="50vw" />'),
        );
        self::assertSame(
            '<figure><picture><source type="image/webp" srcset="/a.webp 800w" sizes="50vw"><img src="/a.png" alt="" sizes="50vw" loading="lazy" decoding="async"></picture></figure>',
            $this->render('<twig:Illustration :candidates="[{src: \'/a.png\', webp: \'/a.webp 800w\'}]" alt="" sizes="50vw" />'),
        );
    }

    public function testNoCandidateRendersNothing(): void
    {
        self::assertSame('', $this->render('<twig:Illustration :candidates="[]" alt="" />'));
    }
}
