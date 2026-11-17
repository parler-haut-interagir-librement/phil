<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\AccountLinks;
use Phil\IdentityProviderBundle\Application\Accounts;
use Phil\IdentityProviderBundle\Application\Exception\Refused;
use Phil\IdentityProviderBundle\Application\Modules;
use Phil\IdentityProviderBundle\Application\SignedLinks;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Phil\IdentityProviderBundle\Infrastructure\Security\SessionListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The pages of accounts: registration, the link that verifies an address, the forgotten password and its reset, and
 * the account page with the name, the address and the password. Changing the address or the password needs a recent
 * proof of the account's strongest method.
 */
final readonly class AccountController
{
    public const string REGISTER = 'phil_identity_provider_register';

    public const string ACCOUNT = 'phil_identity_provider_account';

    public function __construct(
        private Pages $pages,
        private Accounts $accounts,
        private AccountLinks $links,
        private Modules $modules,
        private bool $registration = true,
    ) {
    }

    public function register(Request $request): Response
    {
        if (!$this->registration) {
            throw new NotFoundHttpException('Registration is closed.');
        }

        $errors = [];
        $email = '';

        if ($request->isMethod('POST')) {
            $this->pages->checkCsrf($request);
            $email = $request->request->getString('email');

            try {
                $account = $this->accounts->register($email, $this->modules->has(Modules::PASSWORD) ? $request->request->getString('password') : null, $request->request->getString('name'));

                if ($account instanceof Account) {
                    $this->links->sendVerification($account);
                }

                return $this->pages->render('account/registered', ['email' => $email]);
            } catch (Refused $refused) {
                $errors = $refused->reasons;
            }
        }

        return $this->pages->render('account/register', ['errors' => $errors, 'email' => $email, 'password' => $this->modules->has(Modules::PASSWORD)], [] === $errors ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * The link of the email shows a page that asks to confirm; the address is verified by the form it posts. Opening the
     * link, as the scanners of mail services do, changes nothing.
     */
    public function verifyEmail(Request $request): Response
    {
        $parameters = $request->isMethod('POST') ? $request->request : $request->query;
        $id = $parameters->getString('account');
        $expires = $parameters->getInt('expires');
        $signature = $parameters->getString('signature');
        $account = $this->links->account(SignedLinks::VERIFY, $id, $expires, $signature);

        if (!$account instanceof Account) {
            return $this->pages->render('account/link_invalid', [], Response::HTTP_GONE);
        }

        if (!$request->isMethod('POST')) {
            return $this->pages->render('account/verify', ['link' => ['account' => $id, 'expires' => $expires, 'signature' => $signature]]);
        }

        $this->pages->checkCsrf($request);
        $this->accounts->verifyEmail($account);

        return $this->pages->render('account/verified');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $this->pages->checkCsrf($request);
        $this->links->sendVerification($this->pages->requireAccount());

        return $this->pages->redirect(self::ACCOUNT, ['sent' => 1]);
    }

    public function forgotPassword(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $this->pages->checkCsrf($request);
            $this->links->requestPasswordReset($request->request->getString('email'));

            return $this->pages->render('account/reset_sent');
        }

        return $this->pages->render('account/forgot');
    }

    public function resetPassword(Request $request): Response
    {
        $parameters = $request->isMethod('POST') ? $request->request : $request->query;
        $id = $parameters->getString('account');
        $expires = $parameters->getInt('expires');
        $signature = $parameters->getString('signature');
        $account = $this->links->account(SignedLinks::RESET, $id, $expires, $signature);

        if (!$account instanceof Account) {
            return $this->pages->render('account/link_invalid', [], Response::HTTP_GONE);
        }

        $errors = [];

        if ($request->isMethod('POST')) {
            $this->pages->checkCsrf($request);

            try {
                if (!$this->accounts->resetPassword($account, $request->request->getString('password'))) {
                    return $this->pages->render('account/link_invalid', [], Response::HTTP_GONE);
                }

                return $this->pages->render('account/reset_done');
            } catch (Refused $refused) {
                $errors = $refused->reasons;
            }
        }

        return $this->pages->render('account/reset', ['errors' => $errors, 'link' => ['account' => $id, 'expires' => $expires, 'signature' => $signature]], [] === $errors ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function account(Request $request): Response
    {
        $this->pages->requireAccount();

        return $this->pages->render('account/account', [
            'modules' => $this->modules->enabled(),
            'sent' => $request->query->has('sent'),
            'saved' => $request->query->has('saved'),
            'errors' => [],
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $account = $this->pages->requireAccount();
        $this->pages->checkCsrf($request);

        try {
            $this->accounts->rename($account, $request->request->getString('name'));
        } catch (Refused $refused) {
            return $this->pages->render('account/account', ['modules' => $this->modules->enabled(), 'sent' => false, 'saved' => false, 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::ACCOUNT, ['saved' => 1]);
    }

    public function changeEmail(Request $request): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        try {
            $this->accounts->changeEmail($account, $request->request->getString('email'));
            $this->links->sendVerification($account);
        } catch (Refused $refused) {
            return $this->pages->render('account/account', ['modules' => $this->modules->enabled(), 'sent' => false, 'saved' => false, 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::ACCOUNT, ['sent' => 1]);
    }

    public function changePassword(Request $request): Response
    {
        $account = $this->pages->requireStepUp();
        $this->pages->checkCsrf($request);

        try {
            $this->accounts->changePassword($account, $request->request->getString('password'), keepSession: SessionListener::id($request));
        } catch (Refused $refused) {
            return $this->pages->render('account/account', ['modules' => $this->modules->enabled(), 'sent' => false, 'saved' => false, 'errors' => $refused->reasons], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->redirect(self::ACCOUNT, ['saved' => 1]);
    }
}
