<?php

declare(strict_types=1);

namespace Phil\CommerceProductTemplateBundle\Application;

use Phil\CommerceProductTemplate\Exception\PlanRefused;
use Phil\CommerceProductTemplate\PhotoBox;
use Phil\CommerceProductTemplate\PlacedArtwork;
use Phil\CommerceProductTemplate\Variant;
use Phil\CommerceProductTemplate\VariantPlanner;
use Phil\CommerceProductTemplateBundle\Application\Port\ImageStore;
use Phil\CommerceProductTemplateBundle\Domain\Exception\TemplateRefused;
use Phil\CommerceProductTemplateBundle\Domain\Model\ProductGroup;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductDesignRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductGroupRepository;
use Phil\CommerceProductTemplateBundle\Domain\Repository\ProductTemplateRepository;
use Phil\PrintCompositor\Exception\PrintCompositorException;
use Phil\PrintCompositor\FacePhoto;
use Phil\PrintCompositor\Mockup;
use Phil\PrintCompositor\MockupCompositor;
use Phil\PrintCompositor\PixelBox;
use Phil\PrintCompositor\Placement;
use Phil\PrintCompositor\Raster;

/**
 * Composes the mockups of a group with `phil/print-compositor`: for each active member and each face whose photo its
 * values give, the artworks of its zones that have a photo box, placed on the photo with the fabric's shading, in
 * WebP; the member keeps the path of each.
 *
 * A mockup is named by a digest of what it is made of — the bytes of the photo and artworks, the boxes, the shading
 * and the quality —, so the sizes of a colour share one file, and running it again composes only what changed. It
 * holds no state between two calls.
 */
final readonly class Mockups
{
    public function __construct(
        private ProductTemplateRepository $templates,
        private ProductDesignRepository $designs,
        private ProductGroupRepository $groups,
        private VariantPlanner $planner,
        private ImageStore $images,
        private MockupCompositor $compositor,
        private string $prefix,
        private float $shading,
        private int $quality,
    ) {
    }

    /**
     * @return int the number of mockups composed; those already in the store are not counted
     *
     * @throws TemplateRefused for an unknown group, template or design, a plan refused, an image the store lacks, or a
     *                         mockup the compositor refuses
     */
    public function render(string $design, string $template): int
    {
        $id = ProductGroup::idOf($design, $template);
        $this->groups->find($id) ?? throw TemplateRefused::unknownGroup($id);
        $productTemplate = $this->templates->find($template) ?? throw TemplateRefused::unknownTemplate($template);
        $productDesign = $this->designs->find($design) ?? throw TemplateRefused::unknownDesign($design);

        try {
            $variants = $this->planner->plan($productTemplate->template(), $productDesign->design());
        } catch (PlanRefused $planRefused) {
            throw TemplateRefused::plan($planRefused);
        }

        $bySku = [];

        foreach ($variants as $variant) {
            $bySku[$variant->sku] = $variant;
        }

        $composed = [];
        $bytes = [];

        foreach ($this->groups->membersOf($id) as $member) {
            $variant = $bySku[$member->sku] ?? null;

            if ($member->isActive() && $variant instanceof Variant) {
                $member->showMockups($this->mockupsOf($variant, $composed, $bytes));
            }
        }

        return \count(array_filter($composed));
    }

    /**
     * @param array<string, bool>   $composed the paths seen in this run, and whether this run composed them
     * @param array<string, string> $bytes    the images read in this run, by path
     *
     * @return array<string, string> the path of its mockup, by face code
     */
    private function mockupsOf(Variant $variant, array &$composed, array &$bytes): array
    {
        $mockups = [];

        foreach ($variant->template->faces() as $face) {
            $photo = $variant->photo($face->code);
            $artworks = array_filter($variant->artworks, static fn (PlacedArtwork $artwork): bool => $artwork->face->code === $face->code && $artwork->zone->photoBox instanceof PhotoBox);

            if (null === $photo || [] === $artworks) {
                continue;
            }

            $parts = [hash('sha256', $this->bytes($photo, $bytes)), $this->shading, $this->quality];

            foreach ($artworks as $artwork) {
                $box = $artwork->zone->photoBox;
                \assert($box instanceof PhotoBox);
                $parts[] = $artwork->zone->name.'@'.$box->x.','.$box->y.','.$box->width.','.$box->height.'='.hash('sha256', $this->bytes($artwork->path, $bytes));
            }

            $path = $this->prefix.'/'.substr(hash('sha256', implode("\n", $parts)), 0, 32).'.webp';
            $mockups[$face->code] = $path;

            if (!isset($composed[$path])) {
                $composed[$path] = !$this->images->has($path);

                if ($composed[$path]) {
                    $this->images->write($path, $this->compose($variant, $face->code, $photo, $artworks, $bytes));
                }
            }
        }

        return $mockups;
    }

    /**
     * @param non-empty-array<int, PlacedArtwork> $artworks
     * @param array<string, string>               $bytes
     */
    private function compose(Variant $variant, string $face, string $photo, array $artworks, array &$bytes): string
    {
        try {
            $zones = [];
            $placements = [];

            foreach ($artworks as $artwork) {
                $box = $artwork->zone->photoBox;
                \assert($box instanceof PhotoBox);
                $zones[$artwork->zone->name] = new PixelBox($box->x, $box->y, $box->width, $box->height);
                $placements[] = new Placement($artwork->zone->name, Raster::fromBytes($this->bytes($artwork->path, $bytes)));
            }

            $facePhoto = new FacePhoto($face, $variant->sku, Raster::fromBytes($this->bytes($photo, $bytes)), $zones);

            return $this->compositor->compose(new Mockup($facePhoto, $placements, $this->shading))->webp($this->quality);
        } catch (PrintCompositorException $printCompositorException) {
            throw TemplateRefused::mockup($variant->sku, $face, $printCompositorException);
        }
    }

    /**
     * @param array<string, string> $bytes
     */
    private function bytes(string $path, array &$bytes): string
    {
        return $bytes[$path] ??= $this->images->read($path) ?? throw TemplateRefused::missingImage($path);
    }
}
