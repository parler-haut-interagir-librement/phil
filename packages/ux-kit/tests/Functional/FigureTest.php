<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Several images under one caption, in a grid.
 */
#[CoversNothing]
final class FigureTest extends KitTestCase
{
    public function testTheImagesShareOneCaptionCreditAndLicense(): void
    {
        self::assertSame(
            '<figure><div id="moments" class="isFigureGroup hasThreeColumns wide">'
            .'<img class="isImage" src="/a.jpg" alt="The doors open" width="4" height="4" loading="lazy" decoding="async">'
            .'<img class="isImage" src="/b.jpg" alt="The first guests" width="4" height="4" loading="lazy" decoding="async">'
            .'</div><figcaption>Two moments<small class="isCredit">Ana Lima</small><small class="isLicense"><a href="https://creativecommons.org/licenses/by/4.0/" rel="license">CC BY 4.0</a></small><small class="isCreation" data-digital-source-type="digitalCapture">Photo</small></figcaption></figure>',
            $this->render('<twig:Figure id="moments" class="wide" caption="Two moments" credit="Ana Lima" license="CC BY 4.0" licenseHref="https://creativecommons.org/licenses/by/4.0/" creation="digitalCapture" creationLabel="Photo" :columns="3"><twig:Image src="/a.jpg" alt="The doors open" :width="4" :height="4" /><twig:Image src="/b.jpg" alt="The first guests" :width="4" :height="4" /></twig:Figure>'),
        );
    }

    public function testWithoutACaptionTheGridStandsAloneInTheColumnsTheContainerAllows(): void
    {
        self::assertSame(
            '<div class="isFigureGroup"><img class="isImage" src="/a.jpg" alt="A" width="4" height="4" loading="lazy" decoding="async"></div>',
            $this->render('<twig:Figure :columns="7"><twig:Image src="/a.jpg" alt="A" :width="4" :height="4" /></twig:Figure>'),
        );
        self::assertStringStartsWith('<figure><div class="isFigureGroup hasTwoColumns">', $this->render('<twig:Figure caption="&lt;b&gt;" :columns="2">x</twig:Figure>'));
        self::assertStringStartsWith('<figure><div class="isFigureGroup hasFourColumns">', $this->render('<twig:Figure caption="C" :columns="4">x</twig:Figure>'));
        self::assertStringEndsWith('<figcaption>&lt;b&gt;</figcaption></figure>', $this->render('<twig:Figure :caption="caption">x</twig:Figure>', ['caption' => '<b>']));
    }
}
