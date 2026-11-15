<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Passkeys;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The passkeys of an account: their page, the options and the answer of a registration — JSON, for the Stimulus
 * controller `passkey` —, the options of a sign-in, a new name, and the removal. Adding or removing a passkey needs a
 * recent proof of the account's strongest method; the last way to sign in cannot be removed.
 */
final readonly class PasskeyController
{
    public const string PAGE = 'phil_identity_provider_passkeys';

    public function __construct(
        private Pages $pages,
        private Passkeys $passkeys,
    ) {
    }

    public function page(Request $request): Response
    {
        $account = $this->pages->requireAccount();

        return $this->pages->render('passkey/passkeys', ['passkeys' => $this->passkeys->of($account), 'added' => $request->query->has('added'), 'errors' => []]);
    }

    public function registrationOptions(Request $request): JsonResponse
    {
        $account = $this->pages->requireStepUp();

        if (!$this->pages->isCsrfValid($request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        return Pages::noStore(new JsonResponse($this->passkeys->registrationOptions($account)));
    }

    public function register(Request $request): JsonResponse
    {
        $account = $this->pages->requireStepUp();

        if (!$this->pages->isCsrfValid($request->headers->get('X-CSRF-Token'))) {
            return new JsonResponse(['error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        try {
            $passkey = $this->passkeys->register($account, $request->getContent(), $request->query->getString('name', 'Passkey'));
        } catch (Refused $refused) {
            return Pages::noStore(new JsonResponse(['error' => $refused->reasons[0] ?? 'passkey.refused'], Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return Pages::noStore(new JsonResponse(['id' => $passkey->id(), 'name' => $passkey->name()], Response::HTTP_CREATED));
    }

    public function signInOptions(): JsonResponse
    {
        return Pages::noStore(new JsonResponse($this->passkeys->signInOptions($this->pages->account())));
    }

    public function rename(Request $request, string $id): Response
    {
        $account = $this->pages->requireAccount();
        $this->pages->checkCsrf($request);

        try {
            $this->passkeys->rename($account, $id, $request->request->getString('name'));
        } catch (Refused $refused) {
            return $this->pages->render('passkey/passkeys', ['passkeys' => $this->passkeys->of($account), 'added' => false, 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::PAGE);
    }

    public function remove(Request $request, string $id): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        try {
            $this->passkeys->remove($account, $id);
        } catch (Refused $refused) {
            return $this->pages->render('passkey/passkeys', ['passkeys' => $this->passkeys->of($account), 'added' => false, 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::PAGE);
    }
}
