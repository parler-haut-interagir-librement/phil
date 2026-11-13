<?php

declare(strict_types=1);

namespace Phil\Epub\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Epub\Book;
use Phil\Epub\Identifier;
use Phil\Epub\Metadata;

/**
 * Small values shared by the tests.
 */
final class Books
{
    public const string UUID = '7c9e6679-7425-40de-944b-e07fc1f90ae7';

    public const string PNG = "\x89PNG\r\n\x1A\n\0\0\0\rIHDR\0\0\0\1\0\0\0\1\x08\x06\0\0\0\x1F\x15\xC4\x89\0\0\0\rIDATx\xDAc\xFC\xCF\xC0P\x0F\0\x04\x85\x01\x80\x84\xA9\x8C!\0\0\0\0IEND\xAEB`\x82";

    /** Three MPEG audio frames after an ID3 header. */
    public const string MP3 = "ID3\x03\0\0\0\0\0\0\xFF\xFB\x90\x00";

    /** The `ftyp` box of an MP4 file. */
    public const string MP4 = "\0\0\0\x18ftypmp42\0\0\0\0mp42isom";

    public const string VTT = "WEBVTT\n\n00:00.000 --> 00:01.000\nHello\n";

    public static function modified(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-06 09:30:15', new DateTimeZone('America/Toronto'));
    }

    public static function metadata(string $title = 'A Book', string $language = 'en'): Metadata
    {
        return new Metadata($title, $language, Identifier::uuid(self::UUID), self::modified());
    }

    public static function book(): Book
    {
        return new Book(self::metadata());
    }
}
