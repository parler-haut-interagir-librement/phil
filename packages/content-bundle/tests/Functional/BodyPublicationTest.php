<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Infrastructure\Markup\UjoBodyConverter;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * A body published through the container on SQLite: the draft row keeps its BBCode, the live row stores the UJO,
 * dated with the moment of the publication, and its HTML in the same three columns, and the page shows that HTML.
 */
#[CoversClass(UjoBodyConverter::class)]
final class BodyPublicationTest extends FunctionalTestCase
{
    public function testTheLiveRowStoresTheUjoOfTheDraft(): void
    {
        $contents = $this->contents();
        $page = $contents->create('static_page', 'fr', ['slug' => 'a-propos', 'bodySource' => "[h2]À propos[/h2]\n\nNous [b]jouons[/b].", 'bodyFormat' => 'bbcode']);
        $before = (int) floor(microtime(true) * 1000);
        $contents->publish($page, 'fr');
        $after = (int) ceil(microtime(true) * 1000);
        $this->entityManager()->clear();

        $draft = $contents->draft($page, 'fr');
        self::assertSame('bbcode', $draft['bodyFormat']);
        self::assertSame("[h2]À propos[/h2]\n\nNous [b]jouons[/b].", $draft['bodySource']);

        $live = $contents->live($page, 'fr');
        self::assertSame('ujo', $live['bodyFormat']);
        self::assertIsString($live['bodySource']);
        self::assertSame(1, preg_match('/^\{"time":(\d+),/', $live['bodySource'], $match));
        $time = (int) $match[1];
        self::assertGreaterThanOrEqual($before, $time);
        self::assertLessThanOrEqual($after, $time);
        self::assertSame('{"time":'.$time.',"blocks":[{"id":"b2","type":"header","data":{"text":"À propos","level":2},"tunes":{"anchor":{"anchor":"a-propos"}}},{"id":"b3","type":"paragraph","data":{"text":"Nous <b>jouons</b>."}}]}', $live['bodySource']);
        self::assertSame("<h2 id=\"a-propos\">À propos</h2>\n<p>Nous <b>jouons</b>.</p>", $live['bodyHtml']);
        self::assertStringContainsString('<p>Nous <b>jouons</b>.</p>', (string) $this->request('/fr/a-propos')->getContent());
    }
}
