<?php

declare(strict_types=1);

namespace Phil\AnalyticsBundle\Tests\Unit\Domain\Collection;

use Phil\AnalyticsBundle\Domain\Collection\Admission;
use Phil\AnalyticsBundle\Domain\Collection\AdmissionRule;
use Phil\AnalyticsBundle\Domain\Collection\SiteStatus;
use Phil\AnalyticsBundle\Domain\Source\ReferralKind;
use Phil\AnalyticsBundle\Domain\ValueObject\PrivacySignals;
use Phil\BotDetectionBundle\Domain\Verdict\Verdict;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdmissionRule::class)]
#[CoversClass(Admission::class)]
#[CoversClass(SiteStatus::class)]
final class AdmissionRuleTest extends TestCase
{
    public function testAHumanPageViewOnAnActiveSiteIsAudience(): void
    {
        $admission = new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::none(), false, ReferralKind::Search, Verdict::Human);

        self::assertSame(Admission::CountAudience, $admission);
        self::assertTrue($admission->advancesAudience());
        self::assertFalse($admission->advancesRobotVerdictCounter());
        self::assertFalse($admission->isRefusal());
    }

    /**
     * @return iterable<string, array{SiteStatus}>
     */
    public static function notCollecting(): iterable
    {
        yield 'suspended' => [SiteStatus::Suspended];
        yield 'retired' => [SiteStatus::Retired];
    }

    /**
     * A site that does not collect refuses even a page view that would count nothing anyway: the refusal comes
     * first, so no later step can turn it into a write.
     */
    #[DataProvider('notCollecting')]
    public function testASiteThatDoesNotCollectRefuses(SiteStatus $status): void
    {
        $admission = new AdmissionRule()->decide($status, PrivacySignals::fromHeaders('1', null), true, ReferralKind::Spam, Verdict::Robot);

        self::assertSame(Admission::SiteNotCollecting, $admission);
        self::assertTrue($admission->isRefusal());
        self::assertFalse($admission->advancesAudience());
        self::assertFalse($admission->advancesRobotVerdictCounter());
    }

    /**
     * A browser that opted out is not counted anywhere, not even in the robot-verdict counter.
     */
    public function testAPrivacySignalCountsNothing(): void
    {
        $admission = new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::fromHeaders(null, '1'), false, ReferralKind::Direct, Verdict::Robot);

        self::assertSame(Admission::OptedOut, $admission);
        self::assertFalse($admission->advancesAudience());
        self::assertFalse($admission->advancesRobotVerdictCounter());
    }

    public function testADeclaredCrawlerCountsNothing(): void
    {
        self::assertSame(
            Admission::DeclaredCrawler,
            new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::none(), true, ReferralKind::Direct, Verdict::Human),
        );
    }

    public function testASpamReferrerCountsNothing(): void
    {
        self::assertSame(
            Admission::ReferrerSpam,
            new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::none(), false, ReferralKind::Spam, Verdict::Human),
        );
    }

    public function testARobotVerdictAdvancesOnlyTheRobotVerdictCounter(): void
    {
        $admission = new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::none(), false, ReferralKind::Internal, Verdict::Robot);

        self::assertSame(Admission::RobotVerdictOnly, $admission);
        self::assertTrue($admission->advancesRobotVerdictCounter());
        self::assertFalse($admission->advancesAudience());
    }

    public function testOnlyAnActiveSiteCollects(): void
    {
        self::assertTrue(SiteStatus::Active->collects());
        self::assertFalse(SiteStatus::Suspended->collects());
        self::assertFalse(SiteStatus::Retired->collects());
    }

    /**
     * A detector that failed in the browser sends no verdict: the page view is neither audience nor a robot
     * verdict, so the robot-verdict counter can still fall silent and raise its alarm.
     */
    public function testAPageViewWithoutAUsableVerdictCountsNothing(): void
    {
        $admission = new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::none(), false, ReferralKind::Direct, Verdict::fromSubmitted(null));

        self::assertSame(Admission::NoVerdict, $admission);
        self::assertFalse($admission->advancesAudience());
        self::assertFalse($admission->advancesRobotVerdictCounter());
        self::assertFalse($admission->isRefusal());
    }

    /**
     * The verdict is read strictly: case or padding does not make a human.
     */
    public function testAPaddedVerdictIsNoVerdict(): void
    {
        self::assertSame(
            Admission::NoVerdict,
            new AdmissionRule()->decide(SiteStatus::Active, PrivacySignals::none(), false, ReferralKind::Direct, Verdict::fromSubmitted(' HUMAN ')),
        );
    }
}
