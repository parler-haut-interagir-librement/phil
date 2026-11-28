<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Rule;

use Dom\Element;
use Override;
use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Finding;
use Phil\EmailPreview\Style\InlineStyle;

/**
 * The email does not follow its reader: no remote image of one pixel or less — by its attributes or its style —, and no
 * image address that carries a value of the recipient, as given by the sending, written as is or encoded for an
 * address. An image embedded in the email (`cid:`) or written in it (`data:`) tells the sender nothing.
 */
final readonly class TrackingPixel implements Rule
{
    public const string NAME = 'tracking-pixel';

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function check(CheckedEmail $email): iterable
    {
        foreach ($email->document->getElementsByTagName('img') as $image) {
            $source = trim($image->getAttribute('src') ?? '');

            if (!$this->isRemote($source)) {
                continue;
            }

            if ($this->isTiny($image)) {
                yield Finding::error(self::NAME, 'A remote image of one pixel tracks the opening of the email.', $source);

                continue;
            }

            foreach ($email->sending->recipientTokens as $token) {
                if (false !== stripos($source, $token) || false !== stripos($source, rawurlencode($token))) {
                    yield Finding::error(self::NAME, 'The address of a remote image is proper to the recipient.', $source);

                    break;
                }
            }
        }
    }

    private function isRemote(string $source): bool
    {
        $lowercase = strtolower($source);

        return str_starts_with($lowercase, 'https://') || str_starts_with($lowercase, 'http://') || str_starts_with($lowercase, '//');
    }

    /**
     * Whether the image is drawn at one pixel or less on both sides; a style overrides an attribute, as in a browser.
     */
    private function isTiny(Element $image): bool
    {
        $style = InlineStyle::declarations($image->getAttribute('style') ?? '');
        $width = InlineStyle::pixels($style['width'] ?? $image->getAttribute('width') ?? '');
        $height = InlineStyle::pixels($style['height'] ?? $image->getAttribute('height') ?? '');

        return null !== $width && null !== $height && $width <= 1 && $height <= 1;
    }
}
