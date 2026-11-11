<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\Twig;

use Override;
use Phil\QrCode\Contract\Content;
use Phil\QrCode\Exception\InvalidOption;
use Phil\QrCodeBundle\Application\QrCodes;
use Phil\QrCodeBundle\Infrastructure\Http\QrCodeUrls;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * `qr_code()`, as a function and a filter: the code of a text or a typed content as inline SVG, or as a `data:` URI
 * with `output: 'data_uri'` (SVG) or `'png_data_uri'`; `qr_code_label()`, what a code holds in a few words; and
 * `qr_code_url()`, the signed address of the route that serves it, when the route is imported.
 *
 *     {{ qr_code('https://example.com/tickets/42', {title: 'Ticket 42'}) }}
 *     {{ {type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}|qr_code({logo: 'brand'}) }}
 *     <img src="{{ qr_code(url, {output: 'png_data_uri', scale: 4}) }}" alt="{{ qr_code_label(url) }}">
 */
final class QrCodeExtension extends AbstractExtension
{
    public function __construct(
        private readonly QrCodes $codes,
        private readonly ?QrCodeUrls $urls = null,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('qr_code', $this->qrCode(...), ['is_safe' => ['html']]),
            new TwigFunction('qr_code_label', $this->codes->label(...)),
            new TwigFunction('qr_code_url', $this->url(...)),
        ];
    }

    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('qr_code', $this->qrCode(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * @param string|Content|array<array-key, mixed> $content
     * @param array<string, mixed>                   $options the options of `QrCodes`, and `output`: `svg`, `data_uri` or `png_data_uri`
     */
    public function qrCode(string|array|Content $content, array $options = []): string
    {
        $output = $options['output'] ?? 'svg';
        unset($options['output']);

        return match ($output) {
            'svg' => $this->codes->svg($content, $options),
            'data_uri' => $this->codes->dataUri($content, $options),
            'png_data_uri' => $this->codes->dataUri($content, $options, true),
            default => throw InvalidOption::of('output of qr_code()', 'svg, data_uri or png_data_uri', \is_string($output) ? '"'.$output.'"' : get_debug_type($output)),
        };
    }

    /**
     * @param string|Content|array<array-key, mixed> $content
     */
    public function url(string|array|Content $content, string $format = 'svg', string $logo = '', bool $absolute = false): string
    {
        if (!$this->urls instanceof QrCodeUrls) {
            throw InvalidOption::of('route of qr_code_url()', 'enabled in the configuration, route.enabled', 'none');
        }

        return $this->urls->url($content, $format, $logo, $absolute);
    }
}
