<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Tests\Unit\Application;

use Override;
use Phil\CommerceProductTemplate\Artwork;
use Phil\CommerceProductTemplate\Design;
use Phil\CommerceProductTemplate\Label;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\CatalogGeneration;
use Phil\CommerceProductTemplateBundle\Application\Mockups;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\GroupMember;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Images;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryDesigns;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryGroups;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryImages;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryProducts;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\InMemoryTemplates;
use Phil\CommerceProductTemplateBundle\Tests\Unit\Support\Shirts;
use Phil\PrintCompositor\Colour;
use Phil\PrintCompositor\MockupCompositor;
use Phil\PrintCompositor\Raster;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mockups::class)]
final class MockupsTest extends TestCase
{
    private InMemoryTemplates $templates;

    private InMemoryDesigns $designs;

    private InMemoryGroups $groups;

    private InMemoryImages $images;

    #[Override]
    protected function setUp(): void
    {
        $this->templates = new InMemoryTemplates(Shirts::productTemplate());
        $this->designs = new InMemoryDesigns(Shirts::productDesign());
        $this->groups = new InMemoryGroups();
        new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('NAQ001', 'unisex');
        $this->images = new InMemoryImages();
        $this->images->images = [
            'blanks/front-bk.png' => Images::png(8, 8, 0, 0, 0),
            'blanks/back-bk.png' => Images::png(8, 8, 20, 20, 20),
            'blanks/front-pk.png' => Images::png(8, 8, 240, 160, 190),
            'art/front.png' => Images::png(4, 4, 255, 0, 0),
            'art/pocket.png' => Images::png(4, 4, 0, 255, 0),
            'art/back.png' => Images::png(4, 4, 0, 0, 255),
        ];
    }

    public function testEachColourGetsOneMockupPerFaceItsPhotosShow(): void
    {
        self::assertSame(3, $this->mockups()->render('NAQ001', 'unisex'));

        $black = $this->groups->findMember('NAQ001FOMBK')?->mockups() ?? [];
        $pink = $this->groups->findMember('NAQ001FOMPK')?->mockups() ?? [];

        self::assertSame(['back' => $this->expectedPath('blanks/back-bk.png', ['full@0,0,2,2' => 'art/back.png']), 'front' => $this->expectedPath('blanks/front-bk.png', ['chest@2,2,4,4' => 'art/front.png'])], $black);
        self::assertSame(['front' => $this->expectedPath('blanks/front-pk.png', ['chest@2,2,4,4' => 'art/front.png'])], $pink);
        self::assertSame($black, $this->groups->findMember('NAQ001EC2XBK')?->mockups());
        self::assertCount(9, $this->images->images);

        $mockup = Raster::fromBytes($this->images->images[$black['front']]);
        self::assertSame([8, 8], [$mockup->width, $mockup->height]);
        self::assertEquals(new Colour(0, 0, 0), $mockup->pixel(0, 0));
        self::assertGreaterThan(200, $mockup->pixel(3, 3)->red);
        self::assertLessThan(60, $mockup->pixel(3, 3)->green);
    }

    public function testAFaceWithoutArtworkLeavesTheNextFaceItsMockup(): void
    {
        $this->designs->find('NAQ001')?->redefine(Design::of('NAQ001', Label::of(['en' => 'x']), artworks: [Artwork::of('back', 'art/back.png')]));
        $this->images->images = array_diff_key($this->images->images, ['art/front.png' => true]);
        new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('NAQ001', 'unisex');

        self::assertSame(1, $this->mockups()->render('NAQ001', 'unisex'));
        self::assertSame(['back'], array_keys($this->groups->findMember('NAQ001OMBK')?->mockups() ?? []));
    }

    public function testEachImageIsReadOncePerRun(): void
    {
        $this->mockups()->render('NAQ001', 'unisex');

        self::assertSame(['art/back.png', 'art/front.png', 'blanks/back-bk.png', 'blanks/front-bk.png', 'blanks/front-pk.png'], $this->sorted($this->images->reads));
    }

    public function testRunningAgainComposesOnlyWhatChanged(): void
    {
        $this->mockups()->render('NAQ001', 'unisex');

        self::assertSame(0, $this->mockups()->render('NAQ001', 'unisex'));
        self::assertCount(9, $this->images->images);

        $this->images->images['blanks/front-pk.png'] = Images::png(8, 8, 10, 160, 190);

        self::assertSame(1, $this->mockups()->render('NAQ001', 'unisex'));
        self::assertCount(10, $this->images->images);
    }

    public function testTheShadingAndQualityNameTheMockup(): void
    {
        $this->mockups()->render('NAQ001', 'unisex');
        $first = $this->groups->findMember('NAQ001FOMBK')?->mockups();

        $this->mockups(0.5)->render('NAQ001', 'unisex');
        $shaded = $this->groups->findMember('NAQ001FOMBK')?->mockups();

        $this->mockups(0.5, 90)->render('NAQ001', 'unisex');
        $finer = $this->groups->findMember('NAQ001FOMBK')?->mockups();

        self::assertNotSame($first, $shaded);
        self::assertNotSame($shaded, $finer);
    }

    public function testAWithdrawnMemberKeepsItsMockups(): void
    {
        $member = $this->groups->findMember('NAQ001FOMBK');
        self::assertInstanceOf(GroupMember::class, $member);
        $member->showMockups(['front' => 'old.webp']);
        $member->withdraw();

        $this->groups->addMember(GroupMember::of('NAQ001GONE', 'NAQ001:unisex', ['size' => 'M'], null));

        $this->mockups()->render('NAQ001', 'unisex');

        self::assertSame(['front' => 'old.webp'], $member->mockups());
        self::assertSame([], $this->groups->findMember('NAQ001GONE')?->mockups());
    }

    public function testAnArtworkWithoutPhotoBoxMakesNoMockup(): void
    {
        $this->designs->find('NAQ001')?->redefine(Design::of('NAQ001', Label::of(['en' => 'x']), artworks: [Artwork::of('front', 'art/pocket.png', 'pocket')]));
        new CatalogGeneration($this->templates, $this->designs, $this->groups, new InMemoryProducts(), new VariantPlanner(), 'en')->generate('NAQ001', 'unisex');

        self::assertSame(0, $this->mockups()->render('NAQ001', 'unisex'));
        self::assertSame([], $this->groups->findMember('NAQ001OMBK')?->mockups());
    }

    public function testAMissingImageIsRefused(): void
    {
        unset($this->images->images['art/front.png']);

        $this->expectExceptionObject(TemplateRefused::missingImage('art/front.png'));

        $this->mockups()->render('NAQ001', 'unisex');
    }

    public function testAnImageTheCompositorRefusesIsRefused(): void
    {
        $this->images->images['blanks/front-bk.png'] = Images::png(4, 4, 0, 0, 0);

        $this->expectExceptionObject(new TemplateRefused('The front mockup of NAQ001EC2XBK cannot be composed: The zone "chest" of the front photo of "NAQ001EC2XBK" goes past the 4 × 4 photo.'));

        $this->mockups()->render('NAQ001', 'unisex');
    }

    public function testAnUnknownGroupTemplateDesignOrPlanIsRefused(): void
    {
        $other = new InMemoryDesigns(Shirts::productDesign(Design::of('NAQ001', Label::of(['en' => 'x']), templates: ['fitted'])));

        foreach ([
            [$this->mockups(), 'NAQ002', 'No product group has the identifier NAQ002:unisex.'],
            [$this->mockups(templates: new InMemoryTemplates()), 'NAQ001', 'No product template has the code unisex.'],
            [$this->mockups(designs: new InMemoryDesigns()), 'NAQ001', 'No design has the code NAQ001.'],
            [$this->mockups(designs: $other), 'NAQ001', 'The design NAQ001 is not made on the template unisex.'],
        ] as [$mockups, $design, $message]) {
            try {
                $mockups->render($design, 'unisex');
                self::fail($message);
            } catch (TemplateRefused $refused) {
                self::assertSame($message, $refused->getMessage());
            }
        }
    }

    private function mockups(float $shading = 0.8, int $quality = 82, ?InMemoryTemplates $templates = null, ?InMemoryDesigns $designs = null): Mockups
    {
        return new Mockups($templates ?? $this->templates, $designs ?? $this->designs, $this->groups, new VariantPlanner(), $this->images, new MockupCompositor(), 'mockups', $shading, $quality);
    }

    /**
     * The path a mockup is named by: a digest of its photo, its shading, its quality and each artwork in its box.
     *
     * @param array<string, string> $artworks the path of each artwork, by `zone@x,y,width,height`
     */
    private function expectedPath(string $photo, array $artworks, float $shading = 0.8, int $quality = 82): string
    {
        $parts = [hash('sha256', $this->images->images[$photo]), (string) $shading, (string) $quality];

        foreach ($artworks as $box => $artwork) {
            $parts[] = $box.'='.hash('sha256', $this->images->images[$artwork]);
        }

        return 'mockups/'.substr(hash('sha256', implode("\n", $parts)), 0, 32).'.webp';
    }

    /**
     * @param list<string> $paths
     *
     * @return list<string>
     */
    private function sorted(array $paths): array
    {
        sort($paths);

        return $paths;
    }
}
