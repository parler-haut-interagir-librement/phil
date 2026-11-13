<?php

declare(strict_types=1);

namespace Phil\SvgBlocks;

use Phil\SvgBlocks\Exception\InvalidConfiguration;
use Phil\Ujo\Block;
use SensitiveParameter;

/**
 * The key of a stored drawing: an HMAC-SHA256, under the application's secret, of the block's type, its source and
 * the markup. A drawing whose key does not match was not written by this server for this source — forged by a
 * client, or left behind when the source changed — and is never written in a page.
 *
 * The key is `v1.` and the 43 characters of the HMAC in URL-safe Base64; the prefix lets a later version change the
 * construction and still read the drawings already kept. Comparisons take constant time.
 */
final readonly class Signer
{
    private const string VERSION = 'v1.';

    /**
     * @throws InvalidConfiguration when the secret is shorter than 32 bytes
     */
    public function __construct(
        #[SensitiveParameter]
        private string $secret,
    ) {
        if (\strlen($secret) < 32) {
            throw InvalidConfiguration::of('secret of the drawing keys', 'at least 32 bytes long');
        }
    }

    public function sign(string $type, string $source, string $markup): string
    {
        $mac = hash_hmac('sha256', $type."\0".$source."\0".$markup, $this->secret, true);

        return self::VERSION.sodium_bin2base64($mac, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    /**
     * Whether the markup and its key were written by this server for the block's current source: the closure that
     * `phil/ujo`'s `HtmlRenderer::withStoredMarkup()` takes.
     */
    public function trusts(Block $block, string $markup, string $key): bool
    {
        return hash_equals($this->sign($block->type, Source::of($block), $markup), $key);
    }
}
