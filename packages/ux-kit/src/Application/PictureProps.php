<?php

declare(strict_types=1);

namespace Phil\UxKit\Application;

use InvalidArgumentException;
use Phil\UxKit\Application\Contract\PicturePropsProvider;

/**
 * The props of a `Picture` for an object, from the first provider that supports it, kept to those that describe the
 * image: how it loads and where it stands, `priority`, `loading`, `layout` and the others, stay the page's choice.
 */
final readonly class PictureProps
{
    /** The props a provider may give. */
    public const array KEYS = [
        'src', 'srcset', 'sizes', 'sources', 'dark', 'still', 'forced', 'width', 'height', 'alt', 'caption',
        'description', 'credit', 'license', 'licenseHref', 'creation', 'creationLabel', 'aspect', 'fit', 'position',
        'placeholder', 'preview',
    ];

    /**
     * @param iterable<PicturePropsProvider> $providers
     */
    public function __construct(private iterable $providers = [])
    {
    }

    /**
     * @param array<string, mixed> $options what the template asks of the provider
     *
     * @return array<string, mixed>
     *
     * @throws InvalidArgumentException when no provider supports the object
     */
    public function of(object $subject, array $options = []): array
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($subject)) {
                return array_intersect_key($provider->props($subject, $options), array_flip(self::KEYS));
            }
        }

        throw new InvalidArgumentException(\sprintf('No provider gives the props of a picture for %s: implement %s in a service, which autoconfiguration tags "phil_ux_kit.picture_props".', get_debug_type($subject), PicturePropsProvider::class));
    }
}
