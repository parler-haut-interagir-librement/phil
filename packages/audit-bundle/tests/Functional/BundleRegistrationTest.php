<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Tests\Functional;

use Phil\AuditBundle\PhilAuditBundle;
use Phil\AuditBundle\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Command\Command;

/**
 * The bundle registered in an application without DoctrineBundle: its extension is known under `phil_audit`, its
 * keys are listed, and an unknown key fails the container build.
 */
#[CoversClass(PhilAuditBundle::class)]
final class BundleRegistrationTest extends FunctionalTestCase
{
    public function testTheKernelBootsWithTheBundleUnderItsAlias(): void
    {
        $kernel = self::$kernel;
        self::assertNotNull($kernel);

        $bundle = $kernel->getBundle('PhilAuditBundle');

        self::assertSame('Phil\\AuditBundle', $bundle->getNamespace());
        self::assertSame('phil_audit', $bundle->getContainerExtension()?->getAlias());
    }

    /**
     * The framework's console finds the extension by its alias, with its keys.
     */
    public function testTheConfigurationReferenceListsTheKeys(): void
    {
        $tester = $this->command('config:dump-reference');

        self::assertSame(Command::SUCCESS, $tester->execute(['name' => 'phil_audit']));

        foreach (['connection:', 'serializer:', 'anchor_writer:', 'anchor_reader:', 'anchor_file:', 'dispatch_bus:', 'workflow_segment:'] as $key) {
            self::assertStringContainsString($key, $tester->getDisplay());
        }
    }

    public function testAnUnknownKeyFailsTheContainerBuild(): void
    {
        $kernel = new TestKernel('test', true, ['foo' => true]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessageIsOrContains('Unrecognized option "foo" under "phil_audit". Available options are "anchor_file", "anchor_reader", "anchor_writer", "connection", "dispatch_bus", "feed", "serializer", "site_segments", "workflow_segment".');

        $kernel->boot();
    }
}
