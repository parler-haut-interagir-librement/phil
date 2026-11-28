<?php

declare(strict_types=1);

namespace Phil\BenefitDeliverables\Export;

use DateTimeImmutable;
use Phil\BenefitDeliverables\Exception\InvalidDeliverable;
use SensitiveParameter;

/**
 * The signed tokens of the download links of the exports: `<id>.<expiry>.<signature>`, the signature an HMAC-SHA256
 * of the identifier and the expiry, in unpadded base64url, under a key derived from the secret with HKDF and the label
 * `phil_benefit_deliverables.download_link`, so that no other use of the secret yields the same signatures.
 *
 * A token is read strictly: any other shape, a signature that does not match, compared in constant time, or an expiry
 * passed reads as no token at all.
 */
final readonly class DownloadLinks
{
    private const string TOKEN = '/^([0-9a-f]{32})\.([1-9][0-9]{0,11})\.([A-Za-z0-9_-]{43})$/D';

    /** The HKDF label of the key of the signatures. */
    public const string KEY_LABEL = 'phil_benefit_deliverables.download_link';

    private string $key;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        if (\strlen($secret) < 32) {
            throw InvalidDeliverable::because('The secret of the download links is 32 bytes or more.');
        }

        $this->key = hash_hkdf('sha256', $secret, 32, self::KEY_LABEL);
    }

    public function sign(string $id, DateTimeImmutable $expiresAt): string
    {
        if (!ExportRecord::isId($id)) {
            throw InvalidDeliverable::because('An export is identified by 32 lower-case hexadecimal digits.');
        }

        $expiry = (string) $expiresAt->getTimestamp();

        return $id.'.'.$expiry.'.'.$this->signature($id, $expiry);
    }

    /**
     * The identifier of the export the token names, or `null` when the token is wrong or expired at `$at`.
     */
    public function verify(#[SensitiveParameter] string $token, DateTimeImmutable $at): ?string
    {
        if (1 !== preg_match(self::TOKEN, $token, $parts)) {
            return null;
        }

        [, $id, $expiry, $signature] = $parts;

        if (!hash_equals($this->signature($id, $expiry), $signature) || (int) $expiry <= $at->getTimestamp()) {
            return null;
        }

        return $id;
    }

    private function signature(string $id, string $expiry): string
    {
        return sodium_bin2base64(hash_hmac('sha256', $id.'.'.$expiry, $this->key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
