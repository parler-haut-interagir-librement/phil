<?php

declare(strict_types=1);

namespace Phil\CheckinBundle\Tests\Functional;

use Override;
use Phil\CheckinBundle\Infrastructure\DependencyInjection\CheckinServices;
use Phil\CheckinBundle\Infrastructure\Rules\CheckpointRules;
use Phil\CheckinBundle\PhilCheckinBundle;
use Phil\CheckinBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The rules of the configuration under Symfony's `lint:expressions`: listed with the four variables a rule reads, they
 * pass when they parse, and a rule that reads another variable makes the command fail with the checkpoint's name.
 */
#[CoversClass(PhilCheckinBundle::class)]
#[CoversClass(CheckinServices::class)]
#[CoversClass(CheckpointRules::class)]
final class ExpressionLintTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testTheConfiguredRulesAreLinted(): void
    {
        $tester = $this->lint(self::bootKernel());

        self::assertSame(0, $tester->getStatusCode(), $this->display($tester));
        self::assertStringContainsString('All 1 expressions are valid.', $this->display($tester));

        $language = self::getContainer()->get(CheckinServices::EXPRESSION_LANGUAGE);
        self::assertInstanceOf(ExpressionLanguage::class, $language);
        $rules = self::getContainer()->get(CheckpointRules::class);
        self::assertInstanceOf(CheckpointRules::class, $rules);
        self::assertNull($rules->check('attributes["age"] >= 18 and checkpoint == "hall" and now != null'));
        self::assertStringContainsString('Variable "holder" is not valid', (string) $rules->check(TestKernel::BROKEN));
    }

    public function testABrokenRuleMakesTheLintFail(): void
    {
        $tester = $this->lint(self::createKernel(['environment' => TestKernel::BROKEN_RULE]));

        self::assertNotSame(0, $tester->getStatusCode());
        self::assertStringContainsString('The admission rule of the checkpoint "hall" is not valid', $this->display($tester));
        self::assertStringContainsString('Variable "holder" is not valid', $this->display($tester));
    }

    private function lint(KernelInterface $kernel): ApplicationTester
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $tester = new ApplicationTester($application);
        $tester->run(['command' => 'lint:expressions'], ['decorated' => false]);

        return $tester;
    }

    /**
     * The output on one line: the console wraps long messages at the width of the terminal.
     */
    private function display(ApplicationTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
