<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Functional;

use Override;
use Phil\EnumsBundle\Infrastructure\ExpressionLanguage\EnumsExpressionLanguagesPass;
use Phil\EnumsBundle\PhilEnumsBundle;
use Phil\EnumsBundle\Tests\Functional\App\BrokenExpressionController;
use Phil\EnumsBundle\Tests\Functional\App\src\Enum\ParcelStatus;
use Phil\EnumsBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The expression functions under Symfony's `lint:expressions`: an expression of a controller attribute that calls
 * them passes and evaluates with the language of controller attributes, and one that misspells a function makes the
 * command fail.
 */
#[CoversClass(PhilEnumsBundle::class)]
#[CoversClass(EnumsExpressionLanguagesPass::class)]
final class ExpressionLintTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        FunctionalTestCase::skipWithoutEventDispatcher();
    }

    public function testTheFunctionsPassTheLintAndEvaluateInControllerAttributes(): void
    {
        $tester = $this->lint(self::bootKernel());

        self::assertSame(0, $tester->getStatusCode(), $this->display($tester));
        self::assertMatchesRegularExpression('/All [1-9]\d* expressions are valid\./', $this->display($tester));

        // The language of workflow guards is removed with no guard to evaluate; the pass's unit test covers it.
        $language = self::getContainer()->get('controller.expression_language');
        self::assertInstanceOf(ExpressionLanguage::class, $language);
        self::assertSame(ParcelStatus::Shipped, $language->evaluate('enum_from(value, enum)', ['value' => 'shipped', 'enum' => ParcelStatus::class]));
    }

    public function testAMisspelledFunctionMakesTheLintFail(): void
    {
        $tester = $this->lint(self::bootKernel(['environment' => TestKernel::BROKEN_EXPRESSION]));

        self::assertSame(1, $tester->getStatusCode());
        self::assertStringContainsString(BrokenExpressionController::class.'::cached()', $this->display($tester));
        self::assertStringContainsString('The function "enum_in_grup" does not exist', $this->display($tester));
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
