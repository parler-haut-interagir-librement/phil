<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Domain\Encryption;

/**
 * The algorithm that sealed a value, its second byte in the {@see SealedFormat::Algorithm} format. A value whose byte
 * names no case is refused.
 */
enum SealedAlgorithm: int
{
    /**
     * Envelope encryption through Symfony's KeyManagement component: a fresh data key wrapped by a master key of the
     * KMS, the value encrypted with AES-256-GCM, in the component's self-contained envelope.
     */
    case KeyManagementAes256Gcm = 1;
}
