<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Phil\SitesConsoleBundle\Domain\Status\DeliveryOutcome;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\Status\PlanState;
use Phil\SitesConsoleBundle\Domain\Status\StepOutcome;
use Phil\SitesConsoleBundle\Infrastructure\Twig\KitComponents;
use Phil\SitesConsoleBundle\Infrastructure\Twig\SitesConsoleExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SitesConsoleExtension::class)]
#[CoversClass(KitComponents::class)]
final class TwigTest extends TestCase
{
    public function testTheNamesOfTheRecipes(): void
    {
        self::assertSame('Badge', new SitesConsoleExtension()->kit('Badge'));
        self::assertSame('ui:Table', new SitesConsoleExtension(new KitComponents('/ui/'))->kit('Table'));
        self::assertSame('ui:kit:Card', new SitesConsoleExtension(new KitComponents('ui/kit'))->kit('Card'));
    }

    public function testTheVariantOfEachState(): void
    {
        $extension = new SitesConsoleExtension();
        $variants = [];

        foreach ([...HealthState::cases(), ...StepOutcome::cases(), ...DeliveryOutcome::cases(), ...PlanState::cases()] as $state) {
            $variants[$state::class.'::'.$state->name] = $extension->variant($state);
        }

        self::assertSame(['outline', 'secondary', 'destructive', 'secondary'], \array_slice(array_values($variants), 0, 4));
        self::assertSame(['secondary', 'outline', 'destructive', 'secondary'], \array_slice(array_values($variants), 4, 4));
        self::assertSame(['secondary', 'outline', 'outline', 'destructive'], \array_slice(array_values($variants), 8, 4));
        self::assertSame(['secondary', 'secondary', 'outline', 'destructive'], \array_slice(array_values($variants), 12, 4));
    }
}
