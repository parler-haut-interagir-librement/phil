<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Tests\Unit\Infrastructure;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetsDto;
use Phil\UxEasyAdminBundle\Infrastructure\EasyAdmin\ThemeStylesheet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

#[CoversClass(ThemeStylesheet::class)]
final class ThemeStylesheetTest extends TestCase
{
    public function testTheStylesheetJoinsTheAssetsOfTheContext(): void
    {
        $assets = new AssetsDto();
        $assets->addCssAsset(new AssetDto('styles/admin.css'));

        new ThemeStylesheet('theme.css')($this->event($assets));

        self::assertSame(['styles/admin.css', 'theme.css'], array_keys($assets->getCssAssets()));
        self::assertSame([], $assets->getJsAssets());
    }

    public function testAStylesheetListedByTheDashboardIsKept(): void
    {
        $listed = new AssetDto('theme.css');
        $listed->setHtmlAttribute('media', 'screen');

        $assets = new AssetsDto();
        $assets->addCssAsset($listed);

        new ThemeStylesheet('theme.css')($this->event($assets));

        self::assertSame(['theme.css' => $listed], $assets->getCssAssets());
    }

    public function testARequestOutsideTheBackOfficeIsLeftAlone(): void
    {
        $request = new Request();
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, 'not a context');

        $event = new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);

        new ThemeStylesheet('theme.css')($event);
        new ThemeStylesheet('theme.css')(new RequestEvent(self::createStub(HttpKernelInterface::class), new Request(), HttpKernelInterface::MAIN_REQUEST));

        self::assertSame('not a context', $request->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE));
    }

    private function event(AssetsDto $assets): RequestEvent
    {
        $context = self::createStub(AdminContextInterface::class);
        $context->method('getAssets')->willReturn($assets);
        $request = new Request();
        $request->attributes->set(EA::CONTEXT_REQUEST_ATTRIBUTE, $context);

        return new RequestEvent(self::createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
