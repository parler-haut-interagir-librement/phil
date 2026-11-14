<?php

declare(strict_types=1);

namespace Phil\SvgChart\Internal;

use Override;
use Phil\SvgChart\Exception\RasterizationFailed;
use Phil\SvgChart\Exception\RasterizerUnavailable;
use Phil\SvgChart\Internal\Contract\RasterEngine;
use Resvg\Exception;
use Resvg\Renderer;
use ValueError;

/**
 * The renderer of the `resvg` extension, built once with the fonts given and no other: no font of the system is read,
 * and every image an SVG names must be inside an empty directory, so none is ever read. A refusal of the renderer is a
 * `RasterizationFailed`.
 */
final readonly class ResvgEngine implements RasterEngine
{
    public const string EXTENSION = 'resvg';

    private Renderer $renderer;

    /**
     * @param non-empty-list<string> $fontFiles
     *
     * @throws RasterizerUnavailable when the extension is not loaded
     */
    public function __construct(array $fontFiles, string $family, string $confinedDirectory)
    {
        if (!\extension_loaded(self::EXTENSION)) {
            throw RasterizerUnavailable::extension(self::EXTENSION);
        }

        $this->renderer = new Renderer([
            'fontFiles' => $fontFiles,
            'loadSystemFonts' => false,
            'fontFamily' => $family,
            'serifFamily' => $family,
            'sansSerifFamily' => $family,
            'cursiveFamily' => $family,
            'fantasyFamily' => $family,
            'monospaceFamily' => $family,
            'resourcesDir' => $confinedDirectory,
            'confineResources' => true,
        ]);
    }

    #[Override]
    public function measure(string $svg, int $scale): array
    {
        try {
            return $this->renderer->measure($svg, ['zoom' => (float) $scale]);
        } catch (Exception|ValueError $error) {
            throw RasterizationFailed::because('the renderer refused it, '.$error->getMessage(), $error);
        }
    }

    #[Override]
    public function render(string $svg, int $scale): string
    {
        try {
            return (string) $this->renderer->render($svg, ['zoom' => (float) $scale]);
        } catch (Exception|ValueError $error) {
            throw RasterizationFailed::because('the renderer refused it, '.$error->getMessage(), $error);
        }
    }
}
