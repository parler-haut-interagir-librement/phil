<?php

declare(strict_types=1);

namespace Phil\PhoneBundle\Infrastructure\Twig;

use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneFormat;
use Phil\Phone\PhoneNumber;
use Twig\Attribute\AsTwigFilter;

/**
 * Phone numbers in templates: written as people read them, and as a link. A text is read in the bundle's
 * `default_region`.
 */
final readonly class PhoneExtension
{
    public function __construct(
        private ?string $defaultRegion = null,
    ) {
    }

    /**
     * The number in a format — `international`, `national`, `e164` or `rfc3966` —; an unreadable text as it is.
     */
    #[AsTwigFilter(name: 'phone')]
    public function format(PhoneNumber|string|null $value, string $format = 'international'): string
    {
        return $this->number($value)?->format(PhoneFormat::from($format)) ?? (\is_string($value) ? $value : '');
    }

    /**
     * The `tel:` address of the number, or `null` for an unreadable text.
     */
    #[AsTwigFilter(name: 'phone_link')]
    public function link(PhoneNumber|string|null $value): ?string
    {
        return $this->number($value)?->format(PhoneFormat::Rfc3966);
    }

    private function number(PhoneNumber|string|null $value): ?PhoneNumber
    {
        try {
            return match (true) {
                $value instanceof PhoneNumber => $value,
                null === $value => null,
                default => PhoneNumber::parse($value, $this->defaultRegion),
            };
        } catch (PhoneException) {
            return null;
        }
    }
}
