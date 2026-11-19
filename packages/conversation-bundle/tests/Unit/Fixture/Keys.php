<?php

declare(strict_types=1);

namespace Phil\ConversationBundle\Tests\Unit\Fixture;

use Phil\ConversationBundle\Infrastructure\KeyManagement\KeyManagementMessageSealer;
use Symfony\Component\KeyManagement\KeyLoader\InMemoryKeyLoader;
use Symfony\Component\KeyManagement\Local\SodiumKms;

/**
 * A key management service of two master keys, `k2025` and `k2026`, or of the keys given, each the same whatever the
 * others, and sealers over it.
 */
final class Keys
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $keys
     */
    public static function kms(array $keys = ['k2025', 'k2026']): SodiumKms
    {
        $material = [];

        foreach ($keys as $key) {
            $material[$key] = hash('sha256', $key, true);
        }

        return new SodiumKms(new InMemoryKeyLoader($material));
    }

    /**
     * @param list<string> $keys
     */
    public static function sealer(string $current = 'k2026', array $keys = ['k2025', 'k2026']): KeyManagementMessageSealer
    {
        return new KeyManagementMessageSealer(self::kms($keys), $current);
    }
}
