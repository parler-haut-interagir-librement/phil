<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Tests\Functional;

use Phil\ApiContract\OpenApi\ContractVerifier;
use Phil\ApiContractBundle\PhilApiContractBundle;
use Phil\ApiContractBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;

/**
 * The bundle registered in an application: its extension is known under `phil_api_contract`, its reference lists its
 * keys, and a key it does not know fails the container build.
 */
#[CoversClass(PhilApiContractBundle::class)]
final class BundleRegistrationTest extends FunctionalTestCase
{
    public function testTheKernelBootsWithTheBundleUnderItsAlias(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $bundle = $kernel->getBundle('PhilApiContractBundle');

        self::assertSame('Phil\\ApiContractBundle', $bundle->getNamespace());
        self::assertSame('phil_api_contract', $bundle->getContainerExtension()?->getAlias());
    }

    /**
     * The framework's console finds the extension by its alias, and lists its keys.
     */
    public function testTheConfigurationReferenceListsTheKeys(): void
    {
        $tester = $this->command('config:dump-reference');

        self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'phil_api_contract']));
        self::assertStringContainsString('problem_details:', $tester->getDisplay());
        self::assertStringContainsString('replayed_headers:', $tester->getDisplay());
        self::assertStringContainsString('verification:', $tester->getDisplay());
    }

    /**
     * The verifier is built from its factory, and its command verifies a document in the application's console.
     */
    public function testTheVerifyCommandRunsInTheApplication(): void
    {
        $verifier = self::getContainer()->get(ContractVerifier::class);
        self::assertInstanceOf(ContractVerifier::class, $verifier);
        self::assertCount(17, $verifier->rules);

        $tester = $this->command('phil:api-contract:verify');
        self::assertSame(Command::SUCCESS, $tester->execute(['document' => \dirname(__DIR__).'/Unit/Infrastructure/Fixture/openapi/clean.json']));
        self::assertSame(Command::FAILURE, $tester->execute(['document' => \dirname(__DIR__).'/Unit/Infrastructure/Fixture/openapi/broken.json']));
    }

    public function testAnUnknownKeyFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, ['foo' => true]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "foo" under "phil_api_contract".');

        $kernel->boot();
    }
}
