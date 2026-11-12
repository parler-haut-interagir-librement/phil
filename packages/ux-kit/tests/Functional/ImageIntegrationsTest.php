<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use Phil\UxKit\Tests\Functional\App\Poster;
use Phil\UxKit\Tests\Functional\App\SourceType;
use PHPUnit\Framework\Attributes\CoversNothing;
use stdClass;
use Twig\Error\RuntimeError;

/**
 * The bridges of the image recipes: the `ImageObject` of an image in JSON-LD, the props of a picture from a provider,
 * and image_filters() without LiipImagine.
 */
#[CoversNothing]
final class ImageIntegrationsTest extends KitTestCase
{
    public function testAStructuredImageIsDescribedInJsonLdBeforeItsFigure(): void
    {
        [$html] = $this->renderInRequest('<twig:Image src="/images/bridge.jpg" alt="A bridge at dusk" :width="800" :height="600" caption="The bridge" credit="Photo: Ana Lima" license="CC BY 4.0" licenseHref="https://creativecommons.org/licenses/by/4.0/" :creation="creation" :structured="true" />', ['creation' => SourceType::Captured]);

        self::assertStringStartsWith(
            '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"ImageObject","digitalSourceType":"https://schema.org/DigitalCaptureDigitalSource","contentUrl":"https://www.example.org/images/bridge.jpg","caption":"The bridge","width":800,"height":600,"license":"https://creativecommons.org/licenses/by/4.0/","creditText":"Photo: Ana Lima"}]}</script><figure><img class="isImage" src="/images/bridge.jpg"',
            $html,
        );
        self::assertStringNotContainsString('nonce', $html);
    }

    public function testAStructuredPictureDescribesItsFallbackOnce(): void
    {
        [$html] = $this->renderInRequest('<twig:Picture src="/images/hall.jpg" :sources="[{type: \'image/avif\', srcset: \'/images/hall.avif\'}]" alt="The hall" :width="960" :height="640" :structured="true" />');

        self::assertSame(
            '<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"ImageObject","contentUrl":"https://www.example.org/images/hall.jpg","width":960,"height":640}]}</script>'
            .'<picture><source type="image/avif" srcset="/images/hall.avif"><img class="isImage" src="/images/hall.jpg" alt="The hall" width="960" height="640" loading="lazy" decoding="async"></picture>',
            $html,
        );
    }

    public function testAnImageIsNotDescribedUnlessAsked(): void
    {
        [$html] = $this->renderInRequest('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" caption="A" />');

        self::assertStringNotContainsString('ld+json', $html);
    }

    public function testARefusedImageIsDescribedByNothing(): void
    {
        [$html] = $this->renderInRequest('<twig:Image src="https://elsewhere.example.net/a.jpg" alt="A" :width="1" :height="1" :structured="true" /><twig:Picture src="javascript:alert(1)" alt="A" :width="1" :height="1" :structured="true" />');

        self::assertSame('', $html);
    }

    public function testAnInlineImageIsDescribedByNothing(): void
    {
        [$html] = $this->renderInRequest('<twig:Image src="data:image/png;base64,iVBORw0KGgo=" alt="A dot" :width="1" :height="1" :structured="true" />');

        self::assertStringStartsWith('<img class="isImage"', $html);
    }

    public function testTheTextOfTheDescriptionCannotCloseItsScript(): void
    {
        [$html] = $this->renderInRequest('<twig:Image src="/a.jpg" alt="A" :width="1" :height="1" caption="</script><script>alert(1)</script>" :structured="true" />');

        self::assertSame(1, substr_count($html, '<script'));
        self::assertSame(1, substr_count($html, '</script>'));
    }

    public function testAProviderGivesThePropsOfAPicture(): void
    {
        self::assertSame(
            '<figure><picture><source type="image/avif" srcset="/posters/spring.avif"><img class="isImage" src="/posters/spring.jpg" alt="The poster of spring" width="600" height="900" loading="lazy" decoding="async"></picture><figcaption><small class="isCredit">Studio</small></figcaption></figure>',
            $this->render("<twig:Picture {{ ...picture_props(poster, {credit: 'Studio'}) }} />", ['poster' => new Poster('spring')]),
        );
    }

    public function testAnObjectNoProviderKnowsIsAnError(): void
    {
        try {
            $this->render('<twig:Picture {{ ...picture_props(thing) }} />', ['thing' => new stdClass()]);
            self::fail('Nothing was refused.');
        } catch (RuntimeError $runtimeError) {
            self::assertStringContainsString('No provider gives the props of a picture for stdClass', $runtimeError->getMessage());
        }
    }

    public function testImageFiltersSaysWhenLiipImagineIsMissing(): void
    {
        try {
            $this->render("{{ image_filters('a.jpg', ['small'])|json_encode }}");
            self::fail('Nothing was refused.');
        } catch (RuntimeError $runtimeError) {
            self::assertStringContainsString('image_filters() reads the filter sets of LiipImagine: run "composer require liip/imagine-bundle".', $runtimeError->getMessage());
        }
    }
}
