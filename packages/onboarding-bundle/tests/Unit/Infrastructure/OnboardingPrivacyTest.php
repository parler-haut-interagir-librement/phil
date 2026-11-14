<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use Phil\OnboardingBundle\Infrastructure\Privacy\OnboardingPrivacy;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;
use Phil\PrivacyBundle\Domain\Subject\ErasureReport;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OnboardingPrivacy::class)]
final class OnboardingPrivacyTest extends TestCase
{
    public function testAPersonWithoutProgressHasNothingToExport(): void
    {
        $kit = new Kit();

        self::assertSame([], new OnboardingPrivacy($kit->onboarding, $kit->repository)->export(DataSubject::fromString('nobody')));
    }

    public function testTheModuleIsNamed(): void
    {
        $kit = new Kit();

        self::assertSame('onboarding', new OnboardingPrivacy($kit->onboarding, $kit->repository)->module()->value);
    }

    public function testTheProgressOfAPersonIsExportedStepByStep(): void
    {
        $kit = new Kit();
        $kit->onboarding->complete('ada', 'author', 'profile');
        $kit->clock->modify('+1 minute');
        $kit->onboarding->skip('ada', 'author', 'publish');
        $kit->onboarding->dismiss('ada', 'author');
        $kit->onboarding->complete('ada', 'other', 'first');
        $kit->onboarding->complete('bob', 'author', 'profile');

        $export = new OnboardingPrivacy($kit->onboarding, $kit->repository)->export(DataSubject::fromString('ada'));

        self::assertSame(['progress' => [
            [
                'flow' => 'author',
                'steps' => [
                    'profile' => ['state' => 'completed', 'at' => '2026-11-08T10:00:00+00:00'],
                    'publish' => ['state' => 'skipped', 'at' => '2026-11-08T10:01:00+00:00'],
                ],
                'started_at' => '2026-11-08T10:00:00+00:00',
                'updated_at' => '2026-11-08T10:01:00+00:00',
                'finished_at' => null,
                'dismissed_at' => '2026-11-08T10:01:00+00:00',
            ],
            [
                'flow' => 'other',
                'steps' => ['first' => ['state' => 'completed', 'at' => '2026-11-08T10:01:00+00:00']],
                'started_at' => '2026-11-08T10:01:00+00:00',
                'updated_at' => '2026-11-08T10:01:00+00:00',
                'finished_at' => '2026-11-08T10:01:00+00:00',
                'dismissed_at' => null,
            ],
        ]], $export);
    }

    public function testErasureForgetsThePersonOnly(): void
    {
        $kit = new Kit();
        $kit->onboarding->complete('ada', 'author', 'profile');
        $kit->onboarding->complete('bob', 'author', 'profile');

        $privacy = new OnboardingPrivacy($kit->onboarding, $kit->repository);

        $report = $privacy->erase(DataSubject::fromString('ada'));

        self::assertEquals(ErasureReport::of('onboarding', ['phil_onboarding_progress' => ErasureEffect::Deleted]), $report);
        self::assertSame([], $kit->repository->ofUser('ada'));
        self::assertCount(1, $kit->repository->ofUser('bob'));
    }
}
