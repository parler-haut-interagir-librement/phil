<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\Http;

use SensitiveParameter;
use Symfony\Component\HttpFoundation\UriSigner;

/**
 * The `UriSigner` of the addresses of the images, under a key derived from the secret of the application by
 * HKDF-SHA256 for that usage alone: the `uri_signer` of FrameworkBundle signs the fragments under the secret itself,
 * and an address signed for an image never passes as one signed for another usage.
 */
final class QrCodeUriSigner
{
    /** The usage label the key is derived under. */
    public const string USAGE = 'phil_qr_code.image_url';

    /** The query parameter that carries the signature. */
    public const string PARAMETER = '_qr_signature';

    private function __construct()
    {
    }

    public static function create(#[SensitiveParameter] string $secret): UriSigner
    {
        return new UriSigner(hash_hkdf('sha256', $secret, 32, self::USAGE), self::PARAMETER);
    }
}
