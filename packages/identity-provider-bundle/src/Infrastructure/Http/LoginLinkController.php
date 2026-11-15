<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Application\Event\IdentityEventType;
use Phil\IdentityProviderBundle\Application\Journal;
use Phil\IdentityProviderBundle\Domain\Contract\AccountRepository;
use Phil\IdentityProviderBundle\Domain\Exception\InvalidValue;
use Phil\IdentityProviderBundle\Domain\Model\Account\Account;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

/**
 * The request of a sign-in link: the address typed, a link from the `login_link` authenticator of Symfony Security
 * sent to it, and the same answer whether the address has an account or not. The link itself is checked by that
 * authenticator, on the route `phil_identity_provider_login_link_check`.
 */
final readonly class LoginLinkController
{
    public const string CHECK = 'phil_identity_provider_login_link_check';

    public function __construct(
        private Pages $pages,
        private AccountRepository $accounts,
        private LoginLinkHandlerInterface $links,
        private Journal $journal,
        private ?AccountMailer $mailer = null,
    ) {
    }

    public function request(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->pages->render('login_link/request');
        }

        $this->pages->checkCsrf($request);

        try {
            $account = $this->accounts->findByEmail(Account::normalizeEmail($request->request->getString('email')));
        } catch (InvalidValue) {
            $account = null;
        }

        if ($account instanceof Account && !$account->isDisabled()) {
            $this->mailer?->sendLoginLink($account, $this->links->createLoginLink(new AccountUser($account), $request)->getUrl());
            $this->journal->record(IdentityEventType::LoginLinkSent, $account->id());
        }

        return $this->pages->render('login_link/sent');
    }
}
