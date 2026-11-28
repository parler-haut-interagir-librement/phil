<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Contract;

use Phil\ParticipantSafetyInfo\Exception\UnreadableSafetyInfo;
use SensitiveParameter;

/**
 * Seals and opens the plaintext of safety information. The context, built by the vault from the event and the
 * participant, is authenticated with the text: a sealed text moved to another row does not open.
 *
 * The package's adapter is `KeyManagement\KeyManagementSealer`, the envelope encryption of Symfony's KeyManagement
 * component. An implementation never stores the plaintext and never returns it unauthenticated.
 */
interface SafetyInfoSealer
{
    /**
     * @return string the sealed text, printable, safe to store in a text column
     */
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string;

    /**
     * @throws UnreadableSafetyInfo when the text does not open under this context
     */
    public function open(string $sealed, string $context): string;
}
