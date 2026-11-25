<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Application;

use Phil\FormsAutosave\Domain\ValueObject\DraftKey;
use SensitiveParameter;

/**
 * Derives the names of drafts with a secret of the application, so that neither the browser's storage nor the table
 * tells which form, subject or user a draft belongs to: a key for each form, subject and user, and a hash for each
 * owner on the server.
 */
final readonly class DraftKeys
{
    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_forms_autosave.draft_key';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    /** The length of a key: 32 URL-safe characters, 192 bits of the keyed hash. */
    public const int LENGTH = 32;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    /**
     * The key of a form, filled for a subject — the record it edits, null for a new one — by a user, null for a visitor.
     */
    public function key(string $form, ?string $subject, ?string $user): DraftKey
    {
        $hash = hash_hmac('sha256', json_encode(['key', $form, $subject, $user], \JSON_THROW_ON_ERROR), $this->key, true);

        return DraftKey::of(substr(sodium_bin2base64($hash, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, self::LENGTH));
    }

    /**
     * The hash a user's drafts are kept under on the server, 64 hexadecimal digits.
     */
    public function owner(string $user): string
    {
        return hash_hmac('sha256', json_encode(['owner', $user], \JSON_THROW_ON_ERROR), $this->key);
    }
}
