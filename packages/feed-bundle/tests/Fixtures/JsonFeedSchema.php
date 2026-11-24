<?php

declare(strict_types=1);

namespace Phil\FeedBundle\Tests\Fixtures;

use DateTimeImmutable;
use PHPUnit\Framework\Assert;

/**
 * The JSON Feed 1.1 specification as checks, field by field: the top-level object, its authors and hubs, each item and
 * its attachments — their types, the fields required, the dates in RFC 3339 and the addresses absolute — and no field
 * the specification does not name, unless it starts with an underscore, its rule for extensions.
 */
final class JsonFeedSchema
{
    private const array TOP = ['version' => 'string', 'title' => 'string', 'home_page_url' => 'url', 'feed_url' => 'url', 'description' => 'string', 'user_comment' => 'string', 'next_url' => 'url', 'icon' => 'url', 'favicon' => 'url', 'authors' => 'authors', 'language' => 'string', 'expired' => 'bool', 'hubs' => 'hubs', 'items' => 'items'];

    private const array ITEM = ['id' => 'string', 'url' => 'url', 'external_url' => 'url', 'title' => 'string', 'content_html' => 'string', 'content_text' => 'string', 'summary' => 'string', 'image' => 'url', 'banner_image' => 'url', 'date_published' => 'date', 'date_modified' => 'date', 'authors' => 'authors', 'tags' => 'tags', 'language' => 'string', 'attachments' => 'attachments'];

    private const array ATTACHMENT = ['url' => 'url', 'mime_type' => 'string', 'title' => 'string', 'size_in_bytes' => 'int', 'duration_in_seconds' => 'int'];

    private const array AUTHOR = ['name' => 'string', 'url' => 'url', 'avatar' => 'url'];

    private const array HUB = ['type' => 'string', 'url' => 'url'];

    public static function assertValid(string $json): void
    {
        $document = json_decode($json, true, 32, \JSON_THROW_ON_ERROR);
        Assert::assertIsArray($document);
        self::object('feed', $document, self::TOP, ['version', 'title', 'items']);
        Assert::assertSame('https://jsonfeed.org/version/1.1', $document['version']);
    }

    /**
     * @param array<mixed>          $object
     * @param array<string, string> $fields
     * @param list<string>          $required
     */
    private static function object(string $path, array $object, array $fields, array $required): void
    {
        foreach ($required as $field) {
            Assert::assertArrayHasKey($field, $object, $path.' requires '.$field);
        }

        foreach ($object as $field => $value) {
            Assert::assertIsString($field, $path.' is an object');

            if (str_starts_with($field, '_')) {
                continue;
            }

            Assert::assertArrayHasKey($field, $fields, $path.' has no field '.$field);
            self::value($path.'.'.$field, $value, $fields[$field]);
        }
    }

    private static function value(string $path, mixed $value, string $type): void
    {
        match ($type) {
            'string' => Assert::assertIsString($value, $path),
            'bool' => Assert::assertIsBool($value, $path),
            'int' => Assert::assertIsInt($value, $path),
            'url' => self::url($path, $value),
            'date' => self::date($path, $value),
            'tags' => self::list($path, $value, static fn (string $at, mixed $tag) => Assert::assertIsString($tag, $at)),
            'authors' => self::list($path, $value, static fn (string $at, mixed $author) => self::object($at, self::array($at, $author), self::AUTHOR, [])),
            'hubs' => self::list($path, $value, static fn (string $at, mixed $hub) => self::object($at, self::array($at, $hub), self::HUB, ['type', 'url'])),
            'attachments' => self::list($path, $value, static fn (string $at, mixed $attachment) => self::object($at, self::array($at, $attachment), self::ATTACHMENT, ['url', 'mime_type'])),
            'items' => self::list($path, $value, self::item(...)),
        };
    }

    private static function item(string $path, mixed $item): void
    {
        $item = self::array($path, $item);
        self::object($path, $item, self::ITEM, ['id']);
        Assert::assertTrue(isset($item['content_html']) || isset($item['content_text']), $path.' requires content_html or content_text');
    }

    /**
     * @param callable(string, mixed): void $each
     */
    private static function list(string $path, mixed $value, callable $each): void
    {
        Assert::assertIsList($value, $path);

        foreach ($value as $index => $element) {
            $each($path.'['.$index.']', $element);
        }
    }

    /**
     * @return array<mixed>
     */
    private static function array(string $path, mixed $value): array
    {
        Assert::assertIsArray($value, $path);

        return $value;
    }

    private static function url(string $path, mixed $value): void
    {
        Assert::assertIsString($value, $path);
        Assert::assertMatchesRegularExpression('~^https?://[^\s/]+~', $value, $path.' is an absolute address');
    }

    private static function date(string $path, mixed $value): void
    {
        Assert::assertIsString($value, $path);
        Assert::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:\d{2})$/D', $value, $path.' is RFC 3339');
        Assert::assertInstanceOf(DateTimeImmutable::class, DateTimeImmutable::createFromFormat(\DATE_RFC3339, $value), $path);
    }
}
