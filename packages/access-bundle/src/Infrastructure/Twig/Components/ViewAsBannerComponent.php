<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Twig\Components;

use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\AccessBundle\Infrastructure\Twig\KitComponents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The banner an admin sees while viewing the site as a member, `<twig:PhilAccess:ViewAsBanner />`: who is viewed, that
 * the view only reads, and the form that stops it. Nothing is drawn otherwise. The parts are recipes of the kit.
 */
#[AsTwigComponent(name: 'PhilAccess:ViewAsBanner', template: '@PhilAccess/components/ViewAsBanner.html.twig', exposePublicProps: false)]
final readonly class ViewAsBannerComponent
{
    public const string STOP_ROUTE = 'phil_access_view_as_stop';

    public function __construct(
        private ViewAs $viewAs,
        private KitComponents $kit,
        private UrlGeneratorInterface $urls,
        private CsrfTokenManagerInterface $tokens,
    ) {
    }

    /**
     * The identifier of the member viewed, or null.
     */
    public function member(): ?string
    {
        return $this->viewAs->member()?->value;
    }

    public function stopAction(): string
    {
        return $this->urls->generate(self::STOP_ROUTE);
    }

    public function token(): string
    {
        return $this->tokens->getToken(ViewAsController::TOKEN_ID)->getValue();
    }

    public function kit(string $recipe): string
    {
        return $this->kit->name($recipe);
    }
}
