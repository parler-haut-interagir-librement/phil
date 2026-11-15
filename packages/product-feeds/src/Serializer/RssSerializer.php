<?php

declare(strict_types=1);

namespace Phil\ProductFeeds\Serializer;

use Override;
use Phil\ProductFeeds\Contract\FeedSerializer;
use Phil\ProductFeeds\FeedChannel;
use Phil\ProductFeeds\FeedItem;

/**
 * A feed in RSS 2.0, each item's fields in the `g:` namespace of product data, `http://base.google.com/ns/1.0`; a
 * field without a value is left out, each additional image is an element of its own, and each shipping cost an
 * element holding its sub-fields: `<g:shipping><g:country>CA</g:country><g:price>5.00 CAD</g:price></g:shipping>`.
 */
final readonly class RssSerializer implements FeedSerializer
{
    public const string PRODUCT_NAMESPACE = 'http://base.google.com/ns/1.0';

    #[Override]
    public function header(FeedChannel $channel): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0" xmlns:g="'.self::PRODUCT_NAMESPACE.'">'."\n"
            .'<channel>'."\n"
            .'<title>'.$this->text($channel->title).'</title>'."\n"
            .'<link>'.$this->text($channel->link->toString()).'</link>'."\n"
            .'<description>'.$this->text($channel->description).'</description>'."\n";
    }

    #[Override]
    public function item(FeedItem $item): string
    {
        $xml = '<item>'."\n";

        foreach (Fields::of($item) as $name => $value) {
            foreach ((array) $value as $each) {
                $xml .= '<g:'.$name.'>'.(\is_array($each) ? $this->subFields($each) : $this->text($each)).'</g:'.$name.'>'."\n";
            }
        }

        return $xml.'</item>'."\n";
    }

    #[Override]
    public function footer(): string
    {
        return '</channel>'."\n".'</rss>'."\n";
    }

    /**
     * @param array<string, string> $fields
     */
    private function subFields(array $fields): string
    {
        $xml = '';

        foreach ($fields as $name => $value) {
            $xml .= '<g:'.$name.'>'.$this->text($value).'</g:'.$name.'>';
        }

        return $xml;
    }

    private function text(string $text): string
    {
        return htmlspecialchars($text, \ENT_XML1 | \ENT_QUOTES, 'UTF-8');
    }
}
