<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Tests\Unit\Infrastructure\Twig;

use Phil\TenancyBundle\Application\TenantContext;
use Phil\TenancyBundle\Domain\ValueObject\ReachableTenants;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Phil\TenancyBundle\Infrastructure\Twig\TenancyExtension;
use Phil\TenancyBundle\Tests\Unit\Application\Fixture\FixedReachableTenants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\ArrayLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;

#[CoversClass(TenancyExtension::class)]
final class TenancyExtensionTest extends TestCase
{
    public function testTheTemplateReadsTheActiveAndTheReachableTenants(): void
    {
        $context = new TenantContext();
        $twig = $this->twig($context);

        self::assertSame('none|retail,wholesale', $twig->render('page'));
        self::assertSame(['retail', 'wholesale'], $twig->getRuntime(TenancyExtension::class)->reachable());

        $context->enter(TenantId::fromString('retail'));
        self::assertSame('retail|retail,wholesale', $twig->render('page'));
    }

    private function twig(TenantContext $context): Environment
    {
        $twig = new Environment(new ArrayLoader(['page' => "{{ active_tenant() ?? 'none' }}|{{ reachable_tenants()|join(',') }}"]));
        $extension = new TenancyExtension($context, new FixedReachableTenants(ReachableTenants::of(TenantId::fromString('retail'), TenantId::fromString('wholesale'))));
        $twig->addExtension(new AttributeExtension(TenancyExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([TenancyExtension::class => static fn (): TenancyExtension => $extension]));

        return $twig;
    }
}
