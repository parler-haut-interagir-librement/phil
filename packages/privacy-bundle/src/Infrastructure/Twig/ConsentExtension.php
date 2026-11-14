<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Twig;

use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Infrastructure\Http\ConsentContext;
use Phil\PrivacyBundle\Infrastructure\Http\PrivacyPages;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

/**
 * `privacy_consent('analytics')` reads a consent, `privacy_consent_pending()` says whether the person still has to be
 * asked, `privacy_consent_banner()` renders `@PhilPrivacy/consent/banner.html.twig` when they do, and
 * `privacy_cookie_policy()` renders the cookie inventory, by category, for the site's own privacy policy.
 */
final readonly class ConsentExtension
{
    public function __construct(
        private ConsentContext $context,
        private PrivacyPages $pages,
        private RequestStack $requests,
        private CookieInventory $inventory,
        private ConsentPolicy $policy,
    ) {
    }

    #[AsTwigFunction(name: 'privacy_consent')]
    public function granted(string $category): bool
    {
        return $this->context->state()->isGranted($category);
    }

    #[AsTwigFunction(name: 'privacy_consent_pending')]
    public function pending(): bool
    {
        return $this->context->state()->isPending();
    }

    #[AsTwigFunction(name: 'privacy_consent_banner', needsEnvironment: true, isSafe: ['html'])]
    public function banner(Environment $twig): string
    {
        $state = $this->context->state();

        if (!$state->isPending()) {
            return '';
        }

        $request = $this->requests->getMainRequest();

        return $twig->render('@PhilPrivacy/consent/banner.html.twig', [
            'state' => $state,
            'csrf_token' => $this->pages->csrfToken(),
            'target' => $request instanceof Request ? PrivacyPages::localTarget($request->getRequestUri()) ?? '' : '',
            'groups' => $this->inventory->groups($this->policy),
            'locale' => $request?->getLocale() ?? 'en',
        ]);
    }

    #[AsTwigFunction(name: 'privacy_cookie_policy', needsEnvironment: true, isSafe: ['html'])]
    public function cookiePolicy(Environment $twig): string
    {
        return $twig->render('@PhilPrivacy/cookies/_inventory.html.twig', [
            'groups' => $this->inventory->groups($this->policy),
            'locale' => $this->requests->getMainRequest()?->getLocale() ?? 'en',
        ]);
    }
}
