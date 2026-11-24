<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit;

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
        $english = require __DIR__.'/../../translations/phil_faq.en.php';
        /** @var array<string, string> $french */
        $french = require __DIR__.'/../../translations/phil_faq.fr.php';

        self::assertSame(array_keys($english), array_keys($french));
    }
}
