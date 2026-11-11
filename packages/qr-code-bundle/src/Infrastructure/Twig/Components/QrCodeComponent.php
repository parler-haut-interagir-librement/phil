<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\Twig\Components;

use Phil\QrCode\Content\Url;
use Phil\QrCode\Contract\Content;
use Phil\QrCodeBundle\Application\QrCodes;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * An accessible QR code, `<twig:PhilQrCode:QrCode content="https://example.com/tickets/42" />`: the code in a
 * `<figure>`, and what it holds written beside it in its `<figcaption>`, as a link when it is a web address — so a
 * reader who cannot scan it can still follow it, and a screen reader reads it once.
 *
 * With an `alt`, the image is named by it; without one, the caption says what the image holds and the image is hidden
 * from screen readers; with neither caption nor `alt`, the image is named by the content's label.
 */
#[AsTwigComponent(name: 'PhilQrCode:QrCode', template: '@PhilQrCode/components/QrCode.html.twig', exposePublicProps: false)]
final class QrCodeComponent
{
    /** @var string|array<array-key, mixed> a text, or the array of a typed content */
    public string|array $content = '';

    /** The name of the image for screen readers; the caption names it when empty. */
    public string $alt = '';

    /** Whether what the code holds is written beside it. */
    public bool $caption = true;

    /** The width and height of the image in pixels; the configuration's when null. */
    public ?int $size = null;

    /** The level of error correction, `L`, `M`, `Q` or `H`; the configuration's when null. */
    public ?string $level = null;

    /** The name of a configured logo. */
    public string $logo = '';

    public function __construct(
        private readonly QrCodes $codes,
    ) {
    }

    public function getSvg(): string
    {
        // A null size or level, an empty logo or title, leave the configuration's.
        $options = ['size' => $this->size, 'level' => $this->level, 'logo' => $this->logo, 'title' => $this->alt];
        $svg = $this->codes->svg($this->content, $options);

        return '' === $this->alt && $this->caption ? substr_replace($svg, ' aria-hidden="true"', 4, 0) : $svg;
    }

    /**
     * What the code holds, in a few words.
     */
    public function getLabel(): string
    {
        return $this->codes->label($this->content);
    }

    /**
     * The address to link the caption to, for a web address alone.
     */
    public function getHref(): ?string
    {
        $content = $this->codes->content($this->content);

        if ($content instanceof Url) {
            return $content->payload();
        }

        return !$content instanceof Content && 1 === preg_match('#^https?://[^\s"<>]+$#iD', $content) ? $content : null;
    }
}
