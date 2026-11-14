<?php

declare(strict_types=1);

namespace Phil\CertificatesBundle\Tests\Unit\Support;

use PHPUnit\Framework\Assert;

/**
 * The streams of a PDF the native writer drew, decompressed, for the tests to read its drawing operators.
 */
final class PdfStreams
{
    private function __construct()
    {
    }

    public static function of(string $pdf): string
    {
        Assert::assertStringStartsWith('%PDF-1.7', $pdf);
        preg_match_all('/stream\n(.*?)\nendstream/s', $pdf, $streams);

        return implode("\n%%\n", array_map(static fn (string $stream): string => (string) gzuncompress($stream), $streams[1]));
    }
}
