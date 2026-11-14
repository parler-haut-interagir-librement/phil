<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Benchmark;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedPool;
use Phil\TenancyBundle\Infrastructure\Cache\TenantScopedTagAwarePool;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

/**
 * Every read of `cache.app` goes through the scoped pool: the active tenant read, its pool looked up among the pools
 * kept, the call passed on. Measured against the same read on the pool itself, in a tenant with a readable reference
 * and in one whose reference is digested, and through the tag-aware decorator.
 *
 * @Groups({"tenancy"})
 */
final readonly class TenantScopedPoolBench
{
    private ArrayAdapter $plain;

    private TenantScopedPool $readable;

    private TenantScopedPool $digested;

    private TenantScopedTagAwarePool $tagAware;

    public function __construct()
    {
        $this->plain = new ArrayAdapter();
        $this->plain->get('menu', static fn (): string => 'menu');

        $readable = new TenantContext();
        $readable->enter(TenantId::fromString('retail'));

        $this->readable = new TenantScopedPool(new ArrayAdapter(), $readable);
        $this->readable->get('menu', static fn (): string => 'menu');

        $digested = new TenantContext();
        $digested->enter(TenantId::fromString('Coopérative des producteurs de l’Île-d’Orléans'));

        $this->digested = new TenantScopedPool(new ArrayAdapter(), $digested);
        $this->digested->get('menu', static fn (): string => 'menu');

        $this->tagAware = new TenantScopedTagAwarePool(new TagAwareAdapter(new TenantScopedPool(new ArrayAdapter(), $readable)), $readable);
        $this->tagAware->get('menu', static fn (): string => 'menu');
    }

    public function benchThePoolItself(): void
    {
        $this->plain->get('menu', static fn (): string => 'menu');
    }

    public function benchAReadableTenant(): void
    {
        $this->readable->get('menu', static fn (): string => 'menu');
    }

    public function benchADigestedTenant(): void
    {
        $this->digested->get('menu', static fn (): string => 'menu');
    }

    public function benchTheTagAwareDecorator(): void
    {
        $this->tagAware->get('menu', static fn (): string => 'menu');
    }
}
