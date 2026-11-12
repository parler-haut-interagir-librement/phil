<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Tests\Unit\Infrastructure;

use Phil\MediaBundle\Infrastructure\Admin\ExplorerAccess;
use Phil\MediaBundle\Infrastructure\DependencyInjection\CsrfProtectionPass;
use Phil\MediaBundle\PhilMediaBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\Alias;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;

#[CoversClass(CsrfProtectionPass::class)]
#[CoversClass(PhilMediaBundle::class)]
final class CsrfProtectionPassTest extends TestCase
{
    private function container(bool $guard = true): ContainerBuilder
    {
        $container = new ContainerBuilder();

        if ($guard) {
            $container->setDefinition(ExplorerAccess::class, new Definition(ExplorerAccess::class));
        }

        return $container;
    }

    public function testTheContainerStopsWithAMessageWhenTheCsrfProtectionIsOff(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIsOrContains('phil/media-bundle checks the forms of its file explorer with Symfony\'s CSRF protection, but "framework.csrf_protection" is false. Remove "csrf_protection: false" from the framework configuration or set it to true: the token "phil_media_explorer" is stateless and needs no session. "'.ExplorerAccess::class.'" cannot be built without "security.csrf.token_manager".');

        new CsrfProtectionPass(ExplorerAccess::class, ExplorerAccess::TOKEN_ID)->process($this->container());
    }

    public function testNothingIsSaidWhenTheTokenManagerIsThereOrTheServiceIsNot(): void
    {
        $pass = new CsrfProtectionPass(ExplorerAccess::class, ExplorerAccess::TOKEN_ID);
        $withManager = $this->container();
        $withManager->setDefinition('security.csrf.same_origin_token_manager', new Definition(stdClass::class));
        $withManager->setAlias(CsrfProtectionPass::TOKEN_MANAGER, new Alias('security.csrf.same_origin_token_manager'));

        $without = $this->container(false);

        $pass->process($withManager);
        $pass->process($without);

        self::assertTrue($withManager->hasDefinition(ExplorerAccess::class));
        self::assertFalse($without->hasDefinition(ExplorerAccess::class));
        self::assertSame('security.csrf.token_manager', CsrfProtectionPass::TOKEN_MANAGER);
    }

    public function testTheBundleAddsThePass(): void
    {
        $container = new ContainerBuilder();
        new PhilMediaBundle()->build($container);

        $passes = array_values(array_filter($container->getCompilerPassConfig()->getBeforeOptimizationPasses(), static fn (object $pass): bool => $pass instanceof CsrfProtectionPass));
        self::assertEquals([new CsrfProtectionPass(ExplorerAccess::class, ExplorerAccess::TOKEN_ID)], $passes);
    }
}
