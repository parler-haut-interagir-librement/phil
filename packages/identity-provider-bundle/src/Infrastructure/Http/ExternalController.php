<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\ExternalIdentities;
use Phil\IdentityProviderBundle\Infrastructure\Federation\ExternalProviders;
use Phil\IdentityProviderBundle\Infrastructure\Security\ExternalAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The federated providers: the start of a sign-in or a link with one of them, the page of the identities linked to
 * the account, and the removal of one — never the last way to sign in.
 */
final readonly class ExternalController
{
    public const string PAGE = 'phil_identity_provider_external';

    public function __construct(
        private Pages $pages,
        private ExternalProviders $providers,
        private ExternalIdentities $identities,
        private UrlGeneratorInterface $urls,
    ) {
    }

    public function start(Request $request, string $provider): Response
    {
        $external = $this->providers->get($provider) ?? throw new NotFoundHttpException('No such provider.');

        return Pages::noStore($external->start($request, $this->urls->generate(ExternalAuthenticator::ROUTE, ['provider' => $external->name()], UrlGeneratorInterface::ABSOLUTE_URL)));
    }

    public function page(): Response
    {
        $account = $this->pages->requireAccount();

        return $this->pages->render('external/identities', ['identities' => $this->identities->of($account), 'providers' => $this->providers->all(), 'errors' => []]);
    }

    public function unlink(Request $request, string $id): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        try {
            $this->identities->unlink($account, $id);
        } catch (Refused $refused) {
            return $this->pages->render('external/identities', ['identities' => $this->identities->of($account), 'providers' => $this->providers->all(), 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::PAGE);
    }
}
