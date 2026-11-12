<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Rendering;

use InvalidArgumentException;
use Phil\ContentBundle\Application\Rendering\ContentSchema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentSchema::class)]
final class ContentSchemaTest extends TestCase
{
    public function testAContentIsAWebPageOrAnArticle(): void
    {
        $content = [
            'seoTitle' => 'Le site ouvre',
            'excerptTitle' => 'Ouverture',
            'seoDescription' => 'Une grande ouverture',
            'excerptDescription' => 'Résumé',
            'workflowPublished' => '2026-11-04T12:00:00+00:00',
            'excerptImage' => 'https://example.com/ouverture.png',
            'seoKeywords' => ['larp', 3, 'ouverture'],
            'seoCanonicalUrl' => 'https://example.com/canonique',
        ];

        self::assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            'name' => 'Le site ouvre',
            'description' => 'Une grande ouverture',
            'datePublished' => '2026-11-04T12:00:00+00:00',
            'image' => 'https://example.com/ouverture.png',
            'keywords' => 'larp, ouverture',
            'url' => 'https://example.com/canonique',
        ], ContentSchema::of($content));
        self::assertSame(['NewsArticle', 'Le site ouvre', 'https://example.com/fr/ouverture', 'fr-CA'], array_values(array_intersect_key(ContentSchema::of($content, 'NewsArticle', 'https://example.com/fr/ouverture', 'fr_CA'), array_flip(['@type', 'headline', 'url', 'inLanguage']))));
    }

    public function testWhatIsMissingIsLeftOut(): void
    {
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'WebPage'], ContentSchema::of([]));
        self::assertSame(['@context' => 'https://schema.org', '@type' => 'Article', 'name' => 'Ouverture', 'headline' => 'Ouverture', 'description' => 'Résumé'], ContentSchema::of(['seoTitle' => '', 'excerptTitle' => 'Ouverture', 'seoDescription' => null, 'excerptDescription' => 'Résumé', 'excerptImage' => '/images/local.png', 'seoKeywords' => [], 'workflowPublished' => 7], 'Article'));
        self::assertArrayNotHasKey('keywords', ContentSchema::of(['seoKeywords' => 'larp']));
        self::assertArrayNotHasKey('image', ContentSchema::of(['excerptImage' => 'ftp://example.com/a.png']));
        self::assertSame('http://example.com/a.png', ContentSchema::of(['excerptImage' => 'http://example.com/a.png'])['image']);
        self::assertSame('Ab', ContentSchema::of([], 'Ab')['@type']);

        foreach (['webPage', 'Web Page', 'A', '', "WebPage\n", 'A'.str_repeat('b', 64)] as $type) {
            try {
                ContentSchema::of([], $type);
                self::fail($type);
            } catch (InvalidArgumentException $invalidArgumentException) {
                self::assertSame(\sprintf('"%s" is not a schema.org type.', $type), $invalidArgumentException->getMessage());
            }
        }

        self::assertSame('A'.str_repeat('b', 63), ContentSchema::of([], 'A'.str_repeat('b', 63))['@type']);
    }
}
