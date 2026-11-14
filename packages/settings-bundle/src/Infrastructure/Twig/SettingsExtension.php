<?php

declare(strict_types=1);

namespace Phil\SettingsBundle\Infrastructure\Twig;

use Override;
use Phil\SettingsBundle\Application\DocumentAttributes;
use Phil\SettingsBundle\Application\Settings;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Two Twig functions: `phil_setting('key')`, the value of a setting for the current user or visitor, and
 * `phil_document_attributes()`, the attributes of the page's root element, to write in the layout:
 *
 * ```twig
 * <html lang="{{ app.request.locale }}" {{ phil_document_attributes() }}>
 * ```
 */
final class SettingsExtension extends AbstractExtension
{
    public function __construct(
        private readonly Settings $settings,
        private readonly DocumentAttributes $attributes,
    ) {
    }

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('phil_setting', $this->settings->get(...)),
            new TwigFunction('phil_document_attributes', $this->documentAttributes(...), ['is_safe' => ['html']]),
        ];
    }

    /**
     * The attributes as HTML. A name is the part after `data-` a declaration gives, a value one of its choices, both
     * refused unless lowercase letters, digits, `_` and `-`: neither needs escaping.
     */
    private function documentAttributes(): string
    {
        $written = [];

        foreach ($this->attributes->attributes() as $name => $value) {
            $written[] = \sprintf('%s="%s"', $name, $value);
        }

        return implode(' ', $written);
    }
}
