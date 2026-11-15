<?php

declare(strict_types=1);

namespace Phil\CommerceBundle\Tests\Functional;

use Phil\CommerceBundle\PhilCommerceBundle;
use Phil\CommerceBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;

/**
 * The bundle registered in an application: its extension is known under `phil_commerce`, its one key is the table
 * of tax rates, a rate a `TaxRate` cannot hold fails the container build, and so does any other key.
 */
#[CoversClass(PhilCommerceBundle::class)]
final class BundleRegistrationTest extends FunctionalTestCase
{
    public function testTheKernelBootsWithTheBundleUnderItsAlias(): void
    {
        $bundles = self::getContainer()->getParameter('kernel.bundles');
        self::assertIsArray($bundles);
        self::assertArrayHasKey('PhilCommerceBundle', $bundles);

        $bundle = new PhilCommerceBundle();
        self::assertSame('PhilCommerceBundle', $bundle->getName());
        self::assertSame('phil_commerce', $bundle->getContainerExtension()?->getAlias());
    }

    /**
     * The framework's console finds the extension by its alias, and its reference shows the table of rates.
     */
    public function testTheConfigurationReferenceShowsTheTaxRates(): void
    {
        $tester = $this->command('config:dump-reference');

        self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'phil_commerce']));
        self::assertStringContainsString('tax_rates:', $tester->getDisplay());
    }

    public function testARateATaxRateCannotHoldFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, ['tax_rates' => ['goods' => ['QC' => ['GST' => '5,0']]]]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('"5,0" is not a percentage a tax rate holds');

        $kernel->boot();
    }

    public function testAnUnknownKeyFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, ['foo' => true]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "foo" under "phil_commerce". Available options are "document_types", "gift_wrapping", "offline_payments", "order_expiry", "order_statuses", "payment_links", "receipts", "refunds", "tax_rates".');

        $kernel->boot();
    }
}
