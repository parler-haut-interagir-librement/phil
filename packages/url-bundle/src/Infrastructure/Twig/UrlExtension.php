<?php

declare(strict_types=1);

namespace Phil\UrlBundle\Infrastructure\Twig;

use Phil\Url\Exception\UrlException;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\Url\UrlType;
use Phil\Url\UrlTypes;
use Phil\UrlBundle\Application\AcceptedUrlTypes;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Web addresses in templates: as people read them, and their kind.
 */
final readonly class UrlExtension
{
    /**
     * `example.com/shop` for `https://www.example.com/shop/`; an unreadable text as it is.
     */
    #[AsTwigFilter(name: 'url_display')]
    public function display(TypedUrl|Url|string|null $value): string
    {
        return $this->url($value)?->display() ?? (\is_string($value) ? $value : '');
    }

    /**
     * The kind of an address among the accepted ones — every international kind by default —, or `null`.
     *
     * @param mixed $types an EnumSet, an enum class, cases, or a list of these
     */
    #[AsTwigFunction(name: 'url_type')]
    public function type(TypedUrl|Url|string|null $value, mixed $types = null): ?UrlType
    {
        if ($value instanceof TypedUrl) {
            return $value->type;
        }

        $url = $this->url($value);

        return $url instanceof Url ? UrlTypes::detect($url, ...AcceptedUrlTypes::sets($types)) : null;
    }

    /**
     * The brand's name of an international kind, `YouTube`; the case's name of another.
     */
    #[AsTwigFilter(name: 'url_type_label')]
    public function label(?UrlType $type): string
    {
        return match (true) {
            !$type instanceof UrlType => '',
            $type instanceof KnownUrlType => $type->label(),
            default => $type->name,
        };
    }

    private function url(TypedUrl|Url|string|null $value): ?Url
    {
        try {
            return match (true) {
                $value instanceof TypedUrl => $value->url,
                $value instanceof Url => $value,
                null === $value => null,
                default => Url::fromString($value),
            };
        } catch (UrlException) {
            return null;
        }
    }
}
