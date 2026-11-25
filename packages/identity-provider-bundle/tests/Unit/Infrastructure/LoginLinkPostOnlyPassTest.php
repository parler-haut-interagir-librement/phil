<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Tests\Unit\Infrastructure;

use Phil\IdentityProviderBundle\Infrastructure\DependencyInjection\LoginLinkPostOnlyPass;
use Phil\IdentityProviderBundle\Infrastructure\Http\LoginLinkController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(LoginLinkPostOnlyPass::class)]
final class LoginLinkPostOnlyPassTest extends TestCase
{
    public function testTheAuthenticatorOfTheBundlesRouteChecksByPostOnly(): void
    {
        $container = new ContainerBuilder();
        $ours = $this->authenticator($container, 'main', ['check_route' => LoginLinkController::CHECK, 'check_post_only' => false]);
        $other = $this->authenticator($container, 'admin', ['check_route' => 'app_login_check', 'check_post_only' => false]);
        $unset = new ChildDefinition('security.authenticator.login_link');
        $container->setDefinition('security.authenticator.login_link.bare', $unset);
        $lookalike = new ChildDefinition('security.authenticator.form_login');
        $lookalike->replaceArgument(4, ['check_route' => LoginLinkController::CHECK, 'check_post_only' => false]);

        $container->setDefinition('security.authenticator.form_login.main', $lookalike);
        $container->setDefinition('plain', new Definition(null, [1, 2, 3, 4, ['check_route' => LoginLinkController::CHECK]]));

        new LoginLinkPostOnlyPass()->process($container);

        self::assertSame(['check_post_only' => true, 'check_route' => LoginLinkController::CHECK], $ours->getArguments()['index_4']);
        self::assertSame(['check_route' => 'app_login_check', 'check_post_only' => false], $other->getArguments()['index_4']);
        self::assertSame([], $unset->getArguments());
        self::assertSame(['check_route' => LoginLinkController::CHECK, 'check_post_only' => false], $lookalike->getArguments()['index_4']);
        self::assertSame(['check_route' => LoginLinkController::CHECK], $container->getDefinition('plain')->getArgument(4));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function authenticator(ContainerBuilder $container, string $firewall, array $options): ChildDefinition
    {
        $definition = new ChildDefinition('security.authenticator.login_link');
        $definition->replaceArgument(4, $options);

        $container->setDefinition('security.authenticator.login_link.'.$firewall, $definition);

        return $definition;
    }
}
