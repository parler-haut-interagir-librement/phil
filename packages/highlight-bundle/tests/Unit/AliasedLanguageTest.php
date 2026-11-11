<?php

declare(strict_types=1);

namespace Phil\HighlightBundle\Tests\Unit;

use Phil\Highlight\Token;
use Phil\Highlight\TokenType;
use Phil\HighlightBundle\Infrastructure\Language\AliasedLanguage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AliasedLanguage::class)]
final class AliasedLanguageTest extends TestCase
{
    public function testItReadsAsTheLanguageUnderOtherNames(): void
    {
        $language = new AliasedLanguage(new ShoutLanguage(), ['loud', 'caps']);

        self::assertSame('shout', $language->name());
        self::assertSame(['loud', 'caps'], $language->aliases());
        self::assertEquals([new Token('HEY', TokenType::Keyword)], $language->tokenize('HEY'));
    }
}
