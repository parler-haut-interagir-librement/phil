<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Http;

use LogicException;
use Phil\IdentityProviderBundle\Application\AccountUser;
use Phil\IdentityProviderBundle\Application\Contract\AccountMailer;
use Phil\IdentityProviderBundle\Application\Contract\LinkOrigin;
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
 * sent to it, and the same answer whether the address has an account or not. The link is generated on the canonical
 * origin by `LinkOrigin`, without the request, so that its `Host` header never chooses where the link goes.
 *
 * The link itself is checked by that authenticator, on the route `phil_identity_provider_login_link_check`, by POST
 * only: `LoginLinkPostOnlyPass` sets its `check_post_only`. Following the link — a GET — shows a page with one button
 * that submits it, without script; a mail scanner or a link preview that follows it leaves its single use untouched.
 */
final readonly class LoginLinkController
{
    public const string CHECK = 'phil_identity_provider_login_link_check';

    /**
     * The most query parameters the confirmation page carries over: the four of Symfony's link and a few signed ones.
     */
    public const int MAX_PARAMETERS = 16;

    private const string PARAMETER_NAME = '/^[A-Za-z0-9_.-]{1,64}$/';

    private const int PARAMETER_MAX_LENGTH = 1024;

    public function __construct(
        private Pages $pages,
        private AccountRepository $accounts,
        private LoginLinkHandlerInterface $links,
        private Journal $journal,
        private ?AccountMailer $mailer = null,
        private ?LinkOrigin $origin = null,
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
            $generate = fn (): string => $this->links->createLoginLink(new AccountUser($account))->getUrl();
            $this->mailer?->sendLoginLink($account, $this->origin?->link($generate) ?? $generate());
            $this->journal->record(IdentityEventType::LoginLinkSent, $account->id());
        }

        return $this->pages->render('login_link/sent');
    }

    /**
     * The page a followed link shows: a form that submits the parameters of the link by POST to the check route, which
     * the authenticator answers. It consumes nothing, sends no referrer, and stays out of caches and search engines.
     * A POST that reaches it means the authenticator is missing from the firewall.
     */
    public function confirm(Request $request): Response
    {
        if (!$request->isMethod('GET') && !$request->isMethod('HEAD')) {
            throw new LogicException('The login_link authenticator of the firewall answers this route by POST: add it to the firewall, as the installation guide shows.');
        }

        $link = [];

        foreach ($request->query->all() as $name => $value) {
            if (\count($link) >= self::MAX_PARAMETERS) {
                break;
            }

            // A numeric name is an integer key of PHP: it is read as the text it was.
            $name = \sprintf('%s', $name);

            if (\is_string($value) && \strlen($value) <= self::PARAMETER_MAX_LENGTH && 1 === preg_match(self::PARAMETER_NAME, $name)) {
                $link[$name] = $value;
            }
        }

        $response = $this->pages->render('login_link/confirm', ['link' => $link]);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
