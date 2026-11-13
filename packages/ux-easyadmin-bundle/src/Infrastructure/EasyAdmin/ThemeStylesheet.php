<?php

declare(strict_types=1);

namespace Phil\UxEasyAdminBundle\Infrastructure\EasyAdmin;

use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Context\AdminContextInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\AssetDto;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Links the PHIL UX theme on every page of every dashboard: the stylesheet joins the CSS assets of the request's
 * EasyAdmin context, after the dashboard's and the CRUD controller's own, unless one of them already lists it.
 *
 * The assets belong to the context, which EasyAdmin builds for each request: the listener keeps nothing between two.
 */
final readonly class ThemeStylesheet
{
    public function __construct(private string $path)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        $context = $event->getRequest()->attributes->get(EA::CONTEXT_REQUEST_ATTRIBUTE);

        if (!$context instanceof AdminContextInterface) {
            return;
        }

        $assets = $context->getAssets();

        if (!\array_key_exists($this->path, $assets->getCssAssets())) {
            $assets->addCssAsset(new AssetDto($this->path));
        }
    }
}
