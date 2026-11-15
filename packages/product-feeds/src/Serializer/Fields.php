<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Serializer;

use Phil\Money\Money;
use Phil\ProductFeeds\FeedItem;
use Phil\ProductFeeds\Shipping;
use Phil\Url\Url;

/**
 * An item's fields as the product data specification names and writes them, in the order both formats give them:
 * prices as `12.34 CAD`, dates in ISO 8601, `identifier_exists` as `no` for an item matched by no GTIN nor MPN, the
 * shipping weight as `1500 g`, `is_bundle` as `yes` for a bundle, each custom label in its numbered field.
 *
 * @internal
 */
final class Fields
{
    /** The fields, in their order: the columns of the TSV file. */
    public const array NAMES = [
        'id', 'title', 'description', 'link', 'image_link', 'additional_image_link', 'availability', 'availability_date',
        'price', 'sale_price', 'sale_price_effective_date', 'condition', 'brand', 'gtin', 'mpn', 'identifier_exists',
        'item_group_id', 'color', 'size', 'material', 'pattern', 'product_type', 'google_product_category', 'age_group',
        'gender', 'shipping', 'shipping_weight', 'multipack', 'is_bundle', 'custom_label_0', 'custom_label_1',
        'custom_label_2', 'custom_label_3', 'custom_label_4',
    ];

    /**
     * @return array<string, string|list<string>|list<array<string, string>>|null> by field name, in the order of
     *                                                                             `NAMES`; the additional images as a
     *                                                                             list of addresses, the shipping costs
     *                                                                             as a list of sub-fields by name
     */
    public static function of(FeedItem $item): array
    {
        return [
            'id' => $item->id,
            'title' => $item->title,
            'description' => $item->description,
            'link' => $item->link->toString(),
            'image_link' => $item->imageLink?->toString(),
            'additional_image_link' => array_map(static fn (Url $url): string => $url->toString(), $item->additionalImageLinks),
            'availability' => $item->availability->value,
            'availability_date' => $item->availabilityDate?->format(\DATE_ATOM),
            'price' => self::price($item->price),
            'sale_price' => $item->salePrice instanceof Money ? self::price($item->salePrice) : null,
            'sale_price_effective_date' => $item->salePeriod?->toString(),
            'condition' => $item->condition->value,
            'brand' => $item->brand,
            'gtin' => $item->gtin,
            'mpn' => $item->mpn,
            'identifier_exists' => $item->hasIdentifier() ? null : 'no',
            'item_group_id' => $item->itemGroupId,
            'color' => $item->color,
            'size' => $item->size,
            'material' => $item->material,
            'pattern' => $item->pattern,
            'product_type' => $item->productType,
            'google_product_category' => $item->googleProductCategory,
            'age_group' => $item->ageGroup?->value,
            'gender' => $item->gender?->value,
            'shipping' => array_map(self::shipping(...), $item->shipping),
            'shipping_weight' => null === $item->shippingWeight ? null : $item->shippingWeight.' g',
            'multipack' => null === $item->multipack ? null : (string) $item->multipack,
            'is_bundle' => $item->isBundle ? 'yes' : null,
            'custom_label_0' => $item->customLabels[0] ?? null,
            'custom_label_1' => $item->customLabels[1] ?? null,
            'custom_label_2' => $item->customLabels[2] ?? null,
            'custom_label_3' => $item->customLabels[3] ?? null,
            'custom_label_4' => $item->customLabels[4] ?? null,
        ];
    }

    /**
     * @return array<string, string> the country, the service when there is one, and the price
     */
    private static function shipping(Shipping $shipping): array
    {
        return array_filter(['country' => $shipping->country, 'service' => $shipping->service, 'price' => self::price($shipping->price)], static fn (?string $value): bool => null !== $value);
    }

    private static function price(Money $price): string
    {
        return $price->toDecimal().' '.$price->currency->code;
    }
}
