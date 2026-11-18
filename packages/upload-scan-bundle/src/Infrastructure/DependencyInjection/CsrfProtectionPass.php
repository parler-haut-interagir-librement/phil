<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\UploadScanBundle\Infrastructure\Admin\QuarantineAdminController;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Stops the container with a message that says what to change when the quarantine's administration is registered and
 * FrameworkBundle's CSRF protection is off: its deletions are checked with Symfony's token manager, which then does not
 * exist.
 */
final readonly class CsrfProtectionPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    /**
     * @throws LogicException when the administration is registered and the CSRF protection is off
     */
    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(QuarantineAdminController::class) || $container->has(self::TOKEN_MANAGER)) {
            return;
        }

        throw new LogicException(\sprintf('phil/upload-scan-bundle checks the deletions of its quarantine administration with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Set it to true (the token "%s" is stateless and needs no session), or set "phil_upload_scan.admin.enabled" to false to leave the administration out.', QuarantineAdminController::CSRF_TOKEN_ID));
    }
}
