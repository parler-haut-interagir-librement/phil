<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Benchmark;

use Generator;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;

/**
 * A tenant reference is parsed on every request, when the active tenant is read back from the session, and on every
 * Messenger message that carries one: three Unicode regexes per call.
 *
 * @Groups({"tenancy"})
 */
final class TenantIdBench
{
    /**
     * @ParamProviders("references")
     *
     * @param array{reference: string} $params
     */
    public function benchFromString(array $params): void
    {
        TenantId::fromString($params['reference']);
    }

    /**
     * @return Generator<string, array{reference: string}>
     */
    public function references(): Generator
    {
        yield 'slug (6 bytes)' => ['reference' => 'retail'];
        yield 'uuid (36 bytes)' => ['reference' => '0192f5d4-7c3a-7b21-9e4f-3a6b8c2d1e0f'];
        yield 'unicode name (~60 bytes)' => ['reference' => 'Coopérative des producteurs de l’Île-d’Orléans'];
        yield 'maximum (255 bytes)' => ['reference' => str_repeat('é', 127).'x'];
    }
}
