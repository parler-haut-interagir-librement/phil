<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Tests\Unit\Infrastructure;

use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Phil\UploadScanBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

#[CoversClass(CsrfProtectionPass::class)]
final class CsrfProtectionPassTest extends TestCase
{
    public function testTheAdministrationWithoutTheTokenManagerStopsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->register(QuarantineAdminController::class, QuarantineAdminController::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil/upload-scan-bundle checks the deletions of its quarantine administration with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Set it to true (the token "phil_upload_scan_quarantine" is stateless and needs no session), or set "phil_upload_scan.admin.enabled" to false to leave the administration out.');

        new CsrfProtectionPass()->process($container);
    }

    public function testTheAdministrationWithTheTokenManagerBuilds(): void
    {
        $container = new ContainerBuilder();
        $container->register(QuarantineAdminController::class, QuarantineAdminController::class);
        $container->setAlias(CsrfProtectionPass::TOKEN_MANAGER, 'security.csrf.same_origin_token_manager');
        $container->register('security.csrf.same_origin_token_manager');

        new CsrfProtectionPass()->process($container);

        self::assertTrue($container->hasDefinition(QuarantineAdminController::class));
    }

    public function testWithoutTheAdministrationNoTokenManagerIsNeeded(): void
    {
        $container = new ContainerBuilder();
        $container->setAlias(QuarantineAdminController::class, 'app.admin');

        new CsrfProtectionPass()->process($container);

        self::assertFalse($container->has(CsrfProtectionPass::TOKEN_MANAGER));
    }
}
