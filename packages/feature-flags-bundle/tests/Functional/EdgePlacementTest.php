<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Functional;

use Phil\FeatureFlagsBundle\Infrastructure\Http\FeatureGateListener;
use Phil\FeatureFlagsBundle\Infrastructure\Routing\FlagRoutingCondition;
use Phil\FeatureFlagsBundle\Infrastructure\Twig\FeatureFlagsExtension;
use Phil\FeatureFlagsBundle\Tests\Unit\Fixture\RecordingExposures;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * The flags at the edge of the application: a controller behind a flag or a variant, a template that reads them,
 * a route that matches only while its flag is on, and the exposure of an A/B variant.
 *
 * `tenant-a` is in the membership of `new-checkout` and falls into `one-column` of `checkout-layout`;
 * `tenant-b` is outside the membership and falls into `two-columns`.
 */
#[CoversClass(FeatureGateListener::class)]
#[CoversClass(FlagRoutingCondition::class)]
#[CoversClass(FeatureFlagsExtension::class)]
final class EdgePlacementTest extends FunctionalTestCase
{
    public function testAControllerBehindAnOnFlagAnswers(): void
    {
        $response = $this->request('GET', '/checkout', ['X-Tenant' => 'tenant-a']);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('new checkout', $response->getContent());
    }

    public function testAControllerBehindAnOffFlagIsNotFound(): void
    {
        self::assertSame(404, $this->request('GET', '/checkout', ['X-Tenant' => 'tenant-b'])->getStatusCode());
        self::assertSame(404, $this->request('GET', '/checkout')->getStatusCode());
    }

    public function testAControllerBehindAVariantAnswersOnlyThatArm(): void
    {
        self::assertSame(200, $this->request('GET', '/layout/two-columns', ['X-Tenant' => 'tenant-b'])->getStatusCode());
        self::assertSame(404, $this->request('GET', '/layout/two-columns', ['X-Tenant' => 'tenant-a'])->getStatusCode());
    }

    public function testATemplateReadsTheFlagsOfTheRequest(): void
    {
        self::assertSame("checkout=on\nlayout=one-column\n", $this->request('GET', '/page', ['X-Tenant' => 'tenant-a'])->getContent());
        self::assertSame("checkout=off\nlayout=two-columns\n", $this->request('GET', '/page', ['X-Tenant' => 'tenant-b'])->getContent());
        self::assertSame("checkout=off\nlayout=none\n", $this->request('GET', '/page')->getContent());
    }

    public function testARouteConditionPicksTheNewFeatureWhileItsFlagIsOn(): void
    {
        self::assertSame('beta feature', $this->request('GET', '/feature', ['X-Project' => 'beta-project'])->getContent());
        self::assertSame('current feature', $this->request('GET', '/feature', ['X-Project' => 'other'])->getContent());
    }

    /**
     * Routes are matched before the firewall knows the user: a flag targeted at tenants is refused there, never
     * answered for an anonymous context.
     */
    public function testARouteConditionRefusesAFlagTargetedAtTenants(): void
    {
        self::assertSame(500, $this->request('GET', '/targeted', ['X-Tenant' => 'tenant-a'])->getStatusCode());
    }

    /**
     * Once per flag per request, with the flag and the variant only; a context outside the experiment is not
     * exposed.
     */
    public function testAnExposureIsRecordedOncePerRequest(): void
    {
        $this->request('GET', '/page', ['X-Tenant' => 'tenant-b']);
        $this->request('GET', '/layout/two-columns', ['X-Tenant' => 'tenant-b']);
        $this->request('GET', '/page');

        self::assertSame(
            ['checkout-layout:two-columns', 'checkout-layout:two-columns'],
            $this->service(RecordingExposures::class, RecordingExposures::class)->exposures,
        );
    }
}
