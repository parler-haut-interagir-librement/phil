<?php

declare(strict_types=1);

namespace Phil\ShiftsBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\ShiftsBundle\Infrastructure\Http\ShiftsGuard;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Stops the container from building when the bundle's pages are on and Symfony's CSRF protection, Security or Forms
 * are off: the buttons would otherwise be pressed from any site, the pages would know nobody, and the coordinators
 * would have no form.
 */
final readonly class CsrfProtectionPass implements CompilerPassInterface
{
    public const string TOKEN_MANAGER = 'security.csrf.token_manager';

    public const string AUTHORIZATION = 'security.authorization_checker';

    public const string FORM_FACTORY = 'form.factory';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ShiftsGuard::class)) {
            return;
        }

        if (!$container->has(self::TOKEN_MANAGER)) {
            throw new LogicException('phil/shifts-bundle checks its forms with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Set it to true.');
        }

        if (!$container->has(self::AUTHORIZATION)) {
            throw new LogicException('phil/shifts-bundle checks who volunteers and who coordinates with Symfony Security: install symfony/security-bundle and configure a firewall, or set "phil_shifts.pages" to false.');
        }

        if (!$container->has(self::FORM_FACTORY)) {
            throw new LogicException('phil/shifts-bundle builds the form of a shift with Symfony Forms: install symfony/form and set "framework.form" to true, or set "phil_shifts.pages" to false.');
        }
    }
}
