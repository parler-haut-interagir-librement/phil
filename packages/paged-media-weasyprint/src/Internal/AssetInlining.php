<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Internal;

use Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed;

/**
 * One document's use of the assets directory: its references turned into `data:` addresses, and what it has left of
 * its budget. It lives for one call.
 *
 * @internal
 */
final class AssetInlining
{
    /**
     * What may be taken, by extension — images, fonts and colour profiles: a configuration file left in the directory
     * is never written into a document.
     */
    private const array MEDIA_TYPES = [
        'avif' => 'image/avif',
        'gif' => 'image/gif',
        'icc' => 'application/vnd.iccprofile',
        'icm' => 'application/vnd.iccprofile',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'otf' => 'font/otf',
        'png' => 'image/png',
        'svg' => 'image/svg+xml',
        'ttf' => 'font/ttf',
        'webp' => 'image/webp',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
    ];

    private bool $changed = false;

    /**
     * @param positive-int $maxFileBytes
     */
    public function __construct(
        private readonly string $root,
        private readonly int $maxFileBytes,
        private int $remainingBytes,
    ) {
    }

    public function changed(): bool
    {
        return $this->changed;
    }

    /**
     * Every `url()` of a stylesheet that names a file of the directory written as a `data:` address.
     */
    public function stylesheet(string $css): string
    {
        return preg_replace_callback(
            '/url\(\s*+(?|"([^"]*+)"|\'([^\']*+)\'|([^\s"\'()]++))\s*+\)/i',
            function (array $match): string {
                $address = $this->address($match[1]);

                return null === $address ? $match[0] : 'url("'.$address.'")';
            },
            $css,
        ) ?? $css;
    }

    /**
     * The `data:` address of the file a reference names, or null when the reference is not one to take.
     */
    public function address(string $reference): ?string
    {
        $path = $this->resolve($reference);

        if (null === $path) {
            return null;
        }

        $type = self::MEDIA_TYPES[strtolower(pathinfo($path, \PATHINFO_EXTENSION))] ?? null;

        if (null === $type || !is_file($path) || !is_readable($path)) {
            return null;
        }

        if (filesize($path) > $this->maxFileBytes) {
            throw WeasyPrintFailed::assetTooLarge($reference, $this->maxFileBytes);
        }

        $bytes = (string) file_get_contents($path, length: $this->maxFileBytes);
        $size = \strlen($bytes);

        if ($size > $this->remainingBytes) {
            throw WeasyPrintFailed::assetsOverBudget();
        }

        $this->remainingBytes -= $size;
        $this->changed = true;

        return 'data:'.$type.';base64,'.sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    /**
     * The real path of a relative reference, or null when it has a scheme, starts at a root, carries a query or a
     * fragment, or ends up outside the directory once its links and its `..` are resolved.
     */
    private function resolve(string $reference): ?string
    {
        if (1 === preg_match('~^[a-z][a-z0-9+.-]*:|^[/\\\\]|[?#]~i', $reference)) {
            return null;
        }

        $decoded = rawurldecode($reference);

        if (str_contains($decoded, "\0")) {
            return null;
        }

        $path = realpath($this->root.\DIRECTORY_SEPARATOR.$decoded);

        return false !== $path && str_starts_with($path, $this->root.\DIRECTORY_SEPARATOR) ? $path : null;
    }
}
