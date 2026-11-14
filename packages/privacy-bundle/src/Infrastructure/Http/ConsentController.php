<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Application\Consent\ConsentRecorder;
use Phil\PrivacyBundle\Domain\Consent\ConsentPolicy;
use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Phil\PrivacyBundle\Infrastructure\Routing\PrivacyRoutes;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * The page of the choices, open to anyone at any time so that a consent can be withdrawn as easily as it was given, and
 * the one form behind every button: refusing all, accepting all, saving a choice or granting one more category, as the
 * button of a blocked frame does, is one plain `POST`, which works without JavaScript. The Stimulus controller asks for
 * JSON and stays on the page.
 */
final readonly class ConsentController
{
    public function __construct(
        private PrivacyPages $pages,
        private ConsentContext $context,
        private ConsentCookie $cookie,
        private ConsentRecorder $recorder,
        private ConsentPolicy $policy,
        private CookieInventory $inventory,
    ) {
    }

    public function page(Request $request): Response
    {
        return $this->pages->render('consent/page', ['state' => $this->context->state(), 'locale' => $request->getLocale()]);
    }

    /**
     * The cookie policy: every cookie of the inventory, by category, with its purpose and duration.
     */
    public function cookies(Request $request): Response
    {
        return $this->pages->render('cookies/policy', ['groups' => $this->inventory->groups($this->policy), 'locale' => $request->getLocale()]);
    }

    public function decide(Request $request): Response
    {
        $this->pages->guardForm($request);
        $choice = $this->recorder->decide($this->requested($request), ConsentContext::sendsGlobalPrivacyControl($request));

        $response = str_contains($request->headers->get('Accept', ''), 'application/json')
            ? new JsonResponse(['granted' => $choice->granted, 'version' => $choice->version])
            : $this->redirect($request);

        $response->headers->set('Cache-Control', 'no-store');

        $this->cookie->write($request, $response, $choice);

        return $response;
    }

    /**
     * @return list<string>
     */
    private function requested(Request $request): array
    {
        return match ($request->request->getString('decision')) {
            'accept' => $this->policy->optional(),
            'refuse' => [],
            'save' => array_values(array_filter($request->request->all('categories'), is_string(...))),
            'grant' => [...$this->context->state()->granted(), $request->request->getString('category')],
            default => throw new BadRequestHttpException('The decision must be "accept", "refuse", "save" or "grant".'),
        };
    }

    private function redirect(Request $request): RedirectResponse
    {
        $target = PrivacyPages::localTarget($request->request->getString('_target'));

        return null === $target ? $this->pages->seeOther(PrivacyRoutes::CONSENT) : new RedirectResponse($target, Response::HTTP_SEE_OTHER);
    }
}
