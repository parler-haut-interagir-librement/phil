<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Every message of the bundle exists in English and in French, and the templates use no other.
 */
#[CoversNothing]
final class TranslationsTest extends TestCase
{
    public function testEnglishAndFrenchHoldTheSameMessages(): void
    {
        $english = require \dirname(__DIR__, 2).'/translations/phil_access.en.php';
        $french = require \dirname(__DIR__, 2).'/translations/phil_access.fr.php';
        self::assertIsArray($english);
        self::assertIsArray($french);

        self::assertSame(array_keys($english), array_keys($french));
    }

    public function testTheTemplatesUseKnownMessages(): void
    {
        $english = require \dirname(__DIR__, 2).'/translations/phil_access.en.php';
        self::assertIsArray($english);
        $templates = file_get_contents(\dirname(__DIR__, 2).'/templates/components/ViewAsBanner.html.twig').file_get_contents(\dirname(__DIR__, 2).'/templates/components/AccessSummary.html.twig');
        preg_match_all("/'([a-z_]+\\.[a-z_]+)'\\|trans/", $templates, $matches);

        self::assertCount(10, $matches[1]);

        foreach ($matches[1] as $key) {
            self::assertArrayHasKey($key, $english);
        }
    }
}
