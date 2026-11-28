<?php

declare(strict_types=1);

namespace Phil\ParticipantSafetyInfo\Tests\Support;

use Override;
use Phil\ParticipantSafetyInfo\Contract\SafetyInfoSealer;
use SensitiveParameter;

/**
 * A sealer that seals nothing and keeps the context of every call, to see what the vault binds a text to.
 */
final class RecordingSealer implements SafetyInfoSealer
{
    /** @var list<string> */
    public array $contexts = [];

    #[Override]
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string
    {
        $this->contexts[] = $context;

        return base64_encode($plaintext);
    }

    #[Override]
    public function open(string $sealed, string $context): string
    {
        $this->contexts[] = $context;

        return (string) base64_decode($sealed, true);
    }
}
