<?php

declare(strict_types=1);

namespace Phil\CommerceCatalogBundle\Infrastructure\ProductFeeds;

use InvalidArgumentException;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedFormat;

/**
 * One feed the configuration declares: where its file goes, in which format, what it says of itself, which channel's
 * selection and which locale it publishes, the addresses of each product's page and image, the attributes its fields
 * read, and how long a cache keeps the file `ProductFeedController` serves.
 *
 * The addresses are templates: `{sku}` is the product's SKU, `{family}` its family's — or its own for a product with
 * none —, each percent-encoded.
 */
final readonly class FeedDefinition
{
    /** How long a cache keeps a served feed, in seconds, by default. */
    public const int DEFAULT_MAX_AGE = 3600;

    /** The fields read from a product's attributes, or a variant's axes, by default under their own names. */
    public const array FIELDS = ['description', 'image', 'brand', 'gtin', 'mpn', 'condition', 'availability', 'color', 'size', 'material', 'pattern', 'product_type'];

    /** @var array<string, string> field → attribute */
    public array $attributes;

    /**
     * @param array<string, string> $attributes field → the attribute or axis it reads, for the fields not read under
     *                                          their own names
     */
    public function __construct(
        public string $name,
        public string $path,
        public FeedFormat $format,
        public FeedChannel $channel,
        public string $productLink,
        public ?string $imageLink = null,
        public ?string $salesChannel = null,
        public ?string $locale = null,
        array $attributes = [],
        public bool $allowLocalhost = false,
        public int $maxAge = self::DEFAULT_MAX_AGE,
    ) {
        if ($maxAge < 0) {
            throw new InvalidArgumentException(\sprintf('The feed %s is kept %d seconds: zero or more.', $name, $maxAge));
        }

        if (!str_contains($productLink, '{sku}') && !str_contains($productLink, '{family}')) {
            throw new InvalidArgumentException(\sprintf('The product link of the feed %s names {sku} or {family}.', $name));
        }

        $unknown = array_diff(array_keys($attributes), self::FIELDS);

        if ([] !== $unknown) {
            throw new InvalidArgumentException(\sprintf('The feed %s reads no field %s; its fields are %s.', $name, implode(', ', $unknown), implode(', ', self::FIELDS)));
        }

        $this->attributes = $attributes + array_combine(self::FIELDS, self::FIELDS);
    }

    /**
     * The address a template gives for a product.
     */
    public static function address(string $template, string $sku, ?string $family): string
    {
        return strtr($template, ['{sku}' => rawurlencode($sku), '{family}' => rawurlencode($family ?? $sku)]);
    }
}
