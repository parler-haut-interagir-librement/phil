<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * The two languages of the bundle say the same things.
 */
#[CoversNothing]
final class TranslationsTest extends TestCase
{
    public function testEveryKeyIsInBothLanguages(): void
    {
        /** @var array<string, string> $english */
        $english = require __DIR__.'/../../translations/phil_reactions.en.php';
        /** @var array<string, string> $french */
        $french = require __DIR__.'/../../translations/phil_reactions.fr.php';

        self::assertSame(array_keys($english), array_keys($french));
        self::assertArrayHasKey('reactions.names.choices.configured', $french);
    }
}
