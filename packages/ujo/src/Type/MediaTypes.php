<?php

declare(strict_types=1);

namespace Phil\Ujo\Type;

use Phil\Ujo\Html\LanguageTag;
use Phil\Ujo\Schema\Boolean;
use Phil\Ujo\Schema\Choice;
use Phil\Ujo\Schema\Html;
use Phil\Ujo\Schema\Integer;
use Phil\Ujo\Schema\Link;
use Phil\Ujo\Schema\ListOf;
use Phil\Ujo\Schema\Plain;
use Phil\Ujo\Schema\Property;
use Phil\Ujo\Schema\Shape;

/**
 * The files a page plays: a video and a sound, from the media library or any `http` or `https` address, with their
 * text tracks. Where nothing plays — on paper —, a block shows its poster, its title and its address, with a QR code
 * of the address when `qrCode` is true.
 */
final class MediaTypes
{
    /** The form of a media type, `video/webm; codecs="vp9"`. */
    public const string MEDIA_TYPE = '#^[a-z]+/[a-z0-9.+-]+(?:;[ -~]{1,80})?$#D';

    /** The kinds of a text track. */
    public const array TRACKS = ['captions', 'subtitles', 'descriptions', 'chapters', 'metadata'];

    /**
     * @return list<BlockType>
     */
    public static function all(): array
    {
        return [self::video(), self::audio()];
    }

    /**
     * `{"sources": [{"url", "type"}, ...], "poster": url, "tracks": [...], "controls", "loop", "muted", "width",
     * "height", "title", "caption", "qrCode"}`, the sources in the order a player tries them.
     */
    public static function video(): BlockType
    {
        return new BlockType('video', new Shape([
            ...self::player(),
            'poster' => Property::optional(new Link()),
            'width' => Property::optional(new Integer(1, 10_000)),
            'height' => Property::optional(new Integer(1, 10_000)),
        ]));
    }

    /**
     * `{"sources": [{"url", "type"}, ...], "tracks": [...], "controls", "loop", "muted", "title", "caption",
     * "qrCode"}`.
     */
    public static function audio(): BlockType
    {
        return new BlockType('audio', new Shape(self::player()));
    }

    /**
     * The properties of both players. A track is `{"url", "kind", "language": BCP 47 tag, "label", "default"}`.
     *
     * @return array<string, Property>
     */
    private static function player(): array
    {
        return [
            'sources' => Property::required(new ListOf(new Shape([
                'url' => Property::required(new Link()),
                'type' => Property::optional(new Plain(100, self::MEDIA_TYPE)),
            ]), 1, 8)),
            'tracks' => Property::defaults(new ListOf(new Shape([
                'url' => Property::required(new Link()),
                'kind' => Property::defaults(new Choice(self::TRACKS), 'subtitles'),
                'language' => Property::optional(new Plain(LanguageTag::MAX_LENGTH, LanguageTag::PATTERN)),
                'label' => Property::optional(new Plain(100)),
                'default' => Property::defaults(new Boolean(), false),
            ]), 0, 16), []),
            'controls' => Property::defaults(new Boolean(), true),
            'loop' => Property::defaults(new Boolean(), false),
            'muted' => Property::defaults(new Boolean(), false),
            'title' => Property::optional(new Plain(200)),
            'caption' => Property::defaults(new Html(), ''),
            'qrCode' => Property::optional(new Boolean()),
        ];
    }
}
