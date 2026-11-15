<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Serializer;

use Override;
use Phil\ProductFeeds\Contract\FeedSerializer;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedItem;

/**
 * A feed in tab-separated values: a header row naming the fields, then one row per item, each line ended by a line
 * feed. A tab or a line break inside a value becomes a space; the additional images are one value, separated by
 * commas, a comma inside an address written `%2C`; the shipping costs are one value too, separated by commas, each
 * `country:region:service:price` with no region, `CA::Express:9.00 CAD`. The channel has no place in the file.
 */
final readonly class TsvSerializer implements FeedSerializer
{
    #[Override]
    public function header(FeedChannel $channel): string
    {
        return implode("\t", Fields::NAMES)."\n";
    }

    #[Override]
    public function item(FeedItem $item): string
    {
        $cells = [];

        foreach (Fields::of($item) as $value) {
            $cells[] = \is_array($value)
                ? implode(',', array_map($this->listed(...), $value))
                : str_replace(["\t", "\r\n", "\r", "\n"], ' ', $value ?? '');
        }

        return implode("\t", $cells)."\n";
    }

    /**
     * One value of a list: an address, its commas escaped, or a shipping cost, whose service holds no colon nor comma.
     *
     * @param string|array<string, string> $value
     */
    private function listed(string|array $value): string
    {
        if (\is_string($value)) {
            return str_replace(',', '%2C', $value);
        }

        return $value['country'].'::'.($value['service'] ?? '').':'.$value['price'];
    }

    #[Override]
    public function footer(): string
    {
        return '';
    }
}
