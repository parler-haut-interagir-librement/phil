<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\PhoneVerification;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The phone number of an account: the number typed, a code sent to it by text message, the code typed back, and the
 * removal of the number.
 */
final readonly class PhoneController
{
    public const string PAGE = 'phil_identity_provider_phone';

    public function __construct(
        private Pages $pages,
        private PhoneVerification $verification,
    ) {
    }

    public function page(): Response
    {
        $account = $this->pages->requireAccount();

        return $this->pages->render('phone/phone', ['pending' => $this->verification->pending($account), 'errors' => []]);
    }

    public function start(Request $request): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        try {
            $this->verification->start($account, $request->request->getString('phone'));
        } catch (Refused $refused) {
            return $this->pages->render('phone/phone', ['pending' => null, 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::PAGE);
    }

    public function confirm(Request $request): Response
    {
        $account = $this->pages->requireAccount();
        $this->pages->checkCsrf($request);

        try {
            $this->verification->confirm($account, $request->request->getString('code'));
        } catch (Refused $refused) {
            return $this->pages->render('phone/phone', ['pending' => $this->verification->pending($account), 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::PAGE);
    }

    public function remove(Request $request): RedirectResponse
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);
        $this->verification->remove($account);

        return $this->pages->redirect(self::PAGE);
    }
}
