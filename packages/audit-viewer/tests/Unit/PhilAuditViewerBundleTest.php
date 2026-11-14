<?php

declare(strict_types=1);

namespace Phil\AuditViewer\Tests\Unit;

use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Phil\AuditBundle\Application\AuditLog;
use Phil\AuditBundle\Application\AuditTrail;
use Phil\AuditBundle\Application\ChainStatus;
use Phil\AuditBundle\Application\Contract\ActorResolver;
use Phil\AuditBundle\Application\Contract\PayloadReader;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditHistoryActions;
use Phil\AuditViewer\Infrastructure\EasyAdmin\AuditViewerController;
use Phil\AuditViewer\Infrastructure\Security\AuditViewerVoter;
use Phil\AuditViewer\PhilAuditViewerBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(PhilAuditViewerBundle::class)]
final class PhilAuditViewerBundleTest extends TestCase
{
    public function testTheDefaults(): void
    {
        $container = $this->load([]);
        $optional = static fn (string $id): Reference => new Reference($id, ContainerInterface::NULL_ON_INVALID_REFERENCE);

        self::assertSame('phil_audit_viewer', $this->extension()->getAlias());
        self::assertEquals([new Reference('security.access.decision_manager'), 'ROLE_AUDIT_VIEWER', 'ROLE_AUDIT_VIEWER', null, null], $container->getDefinition(AuditViewerVoter::class)->getArguments());
        self::assertTrue($container->getDefinition(AuditViewerVoter::class)->hasTag('security.voter'));
        self::assertEquals([new Reference('router'), new Reference('security.authorization_checker')], $container->getDefinition(AuditHistoryActions::class)->getArguments());
        self::assertTrue($container->getDefinition(AuditHistoryActions::class)->hasTag(EasyAdminBundle::TAG_ACTIONS_EXTENSION));

        $controller = $container->getDefinition(AuditViewerController::class);
        self::assertEquals([
            new Reference('twig'),
            new Reference(AuditLog::class),
            new Reference(ChainStatus::class),
            new Reference('security.authorization_checker'),
            new Reference('security.csrf.token_manager'),
            new Reference('clock'),
            date_default_timezone_get(),
            50,
            'audit',
            100000,
            $optional(AuditTrail::class),
            $optional(ActorResolver::class),
            $optional(PayloadReader::class),
        ], $controller->getArguments());
        self::assertTrue($controller->hasTag('controller.service_arguments'));
        self::assertTrue($controller->hasTag(EasyAdminBundle::TAG_ADMIN_ROUTE_CONTROLLER));
        self::assertTrue($controller->isPublic());
    }

    public function testEveryKey(): void
    {
        $container = $this->load(['role' => 'ROLE_AUDITOR', 'export_role' => 'ROLE_COMPLIANCE', 'history_role' => 'ROLE_ADMIN', 'access_policy' => 'app.audit_policy', 'history_action' => false, 'export_segment' => 'audit.exports', 'export_limit' => 10, 'page_size' => 500, 'timezone' => 'Europe/Paris']);

        self::assertEquals([new Reference('security.access.decision_manager'), 'ROLE_AUDITOR', 'ROLE_COMPLIANCE', 'ROLE_ADMIN', new Reference('app.audit_policy')], $container->getDefinition(AuditViewerVoter::class)->getArguments());
        self::assertFalse($container->hasDefinition(AuditHistoryActions::class), 'No action when it is turned off.');
        self::assertSame(['Europe/Paris', 500, 'audit.exports', 10], \array_slice($container->getDefinition(AuditViewerController::class)->getArguments(), 6, 4));
        self::assertSame(1, $this->load(['export_limit' => 1, 'page_size' => 1])->getDefinition(AuditViewerController::class)->getArgument(7));
    }

    public function testWrongValuesAreRefused(): void
    {
        foreach ([
            ['role' => ''],
            ['export_segment' => 'Audit'],
            ['export_segment' => ''],
            ['export_segment' => "audit\n"],
            ['export_segment' => 'a'.str_repeat('b', 64)],
            ['export_limit' => 0],
            ['page_size' => 0],
            ['page_size' => 501],
        ] as $config) {
            try {
                $this->load($config);
                self::fail((string) json_encode($config));
            } catch (InvalidConfigurationException $invalidConfigurationException) {
                self::assertStringContainsString('phil_audit_viewer', $invalidConfigurationException->getMessage());
            }
        }

        self::assertSame('a'.str_repeat('b', 63), $this->load(['export_segment' => 'a'.str_repeat('b', 63)])->getDefinition(AuditViewerController::class)->getArgument(8));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $this->extension()->load([$config], $container);

        return $container;
    }

    private function extension(): ExtensionInterface
    {
        $extension = new PhilAuditViewerBundle()->getContainerExtension();
        self::assertNotNull($extension);

        return $extension;
    }
}
