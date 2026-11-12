<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Functional;

use Override;
use Phil\AnalyticsBundle\Domain\Collection\Admission;
use Phil\AnalyticsBundle\Domain\Collection\AdmissionRule;
use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Reading\SmallNumberSuppression;
use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\Source\ReferrerClassifier;
use Phil\AnalyticsBundle\Domain\ValueObject\HostList;
use Phil\AnalyticsBundle\Domain\ValueObject\Origin;
use Phil\AnalyticsBundle\Domain\ValueObject\PrivacySignals;
use Phil\AnalyticsBundle\Domain\ValueObject\SmallNumberThreshold;
use Phil\AnalyticsBundle\Infrastructure\DependencyInjection\AnalyticsServices;
use Phil\AnalyticsBundle\Tests\Functional\App\TestKernel;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The stateless domain services, wired by the bundle from `phil_analytics`, then used together the way a collection
 * endpoint and a dashboard would.
 */
#[CoversClass(AnalyticsServices::class)]
#[CoversClass(AdmissionRule::class)]
#[CoversClass(ReferrerClassifier::class)]
#[CoversClass(HostList::class)]
#[CoversClass(SmallNumberSuppression::class)]
#[CoversClass(SmallNumberThreshold::class)]
final class ApplicationWiringTest extends FunctionalTestCase
{
    #[Override]
    protected static function analyticsConfig(): array
    {
        return ['hosts' => ['spam' => ['free-traffic.test']]];
    }

    public function testAPageViewFromASearchEngineIsClassifiedThenCountedAsAudience(): void
    {
        $classifier = $this->service(ReferrerClassifier::class, ReferrerClassifier::class);
        $source = $classifier->classify('https://www.google.ca/search?q=x', [Origin::fromString('https://example.com')]);

        self::assertSame(ReferralKind::Search, $source);
        self::assertSame(
            Admission::CountAudience,
            $this->service(AdmissionRule::class, AdmissionRule::class)->decide(SiteStatus::Active, PrivacySignals::none(), false, $source, Verdict::Human),
        );
    }

    public function testASocialNetworkOfTheDefaultListIsClassified(): void
    {
        self::assertSame(ReferralKind::Social, $this->service(ReferrerClassifier::class, ReferrerClassifier::class)->classify('https://mastodon.social/@someone', []));
    }

    /**
     * The spam list comes from the configuration: a referrer on it is refused before anything is counted.
     */
    public function testAReferrerOnTheConfiguredSpamListCountsNothing(): void
    {
        $source = $this->service(ReferrerClassifier::class, ReferrerClassifier::class)->classify('https://free-traffic.test/', []);

        self::assertSame(ReferralKind::Spam, $source);
        self::assertSame(
            Admission::ReferrerSpam,
            $this->service(AdmissionRule::class, AdmissionRule::class)->decide(SiteStatus::Active, PrivacySignals::none(), false, $source, Verdict::Human),
        );
    }

    public function testTheSuppressionReadsTheConfiguredThreshold(): void
    {
        $result = $this->service(SmallNumberSuppression::class, SmallNumberSuppression::class)->apply(['CA' => 5, 'BE' => 4, 'CH' => 1]);

        self::assertSame(['CA' => 5], $result->shown);
        self::assertSame(5, $result->others);
        self::assertSame(0, $result->hidden);
    }

    public function testAThresholdUnderTwoFailsTheContainerBuild(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('The value 1 is too small for path "phil_analytics.small_number_threshold". Should be greater than or equal to 2');

        new TestKernel('test', true, ['small_number_threshold' => 1])->boot();
    }

    /**
     * A host list entry the value object refuses still compiles: the refusal surfaces when the service is first built.
     */
    public function testAnInvalidHostIsRefusedWhenTheServiceIsFirstBuilt(): void
    {
        $kernel = new TestKernel('test', true, ['hosts' => ['spam' => ['not a host']]]);
        $kernel->boot();

        try {
            $this->expectException(InvalidValue::class);
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $container->get(ReferrerClassifier::class);
        } finally {
            $kernel->shutdown();
        }
    }
}
