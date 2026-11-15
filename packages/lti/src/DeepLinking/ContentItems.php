<?php

declare(strict_types=1);

namespace Phil\Lti\DeepLinking;

use Phil\Lti\Contract\ContentItem;
use Phil\Lti\Support\Fields;

/**
 * Reads the content items claim of a deep linking response into typed items. An item of a type this package does not
 * know is skipped, as the specification lets a platform do.
 */
final class ContentItems
{
    /**
     * The most items read from one response.
     */
    public const int MAX_ITEMS = 100;

    /**
     * @param array<array-key, mixed> $claims the claims of the response, verified
     *
     * @return list<ContentItem>
     */
    public static function fromClaims(array $claims, string $claim, bool $allowLocalhost = false): array
    {
        $items = [];

        foreach (new Fields($claims, allowLocalhost: $allowLocalhost)->objectList($claim, self::MAX_ITEMS) as $fields) {
            $item = match ($fields->optionalString('type', 32)) {
                ResourceLinkItem::TYPE => ResourceLinkItem::read($fields),
                LinkItem::TYPE => LinkItem::read($fields),
                FileItem::TYPE => FileItem::read($fields),
                HtmlItem::TYPE => HtmlItem::read($fields),
                ImageItem::TYPE => ImageItem::read($fields),
                default => null,
            };

            if (null !== $item) {
                $items[] = $item;
            }
        }

        return $items;
    }
}
