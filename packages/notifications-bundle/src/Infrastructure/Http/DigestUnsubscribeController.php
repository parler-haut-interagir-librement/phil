<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\Http;

use Phil\NotificationsBundle\Application\Contract\DigestUnsubscriber;
use Phil\NotificationsBundle\Application\NotificationType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The signed link of a digest, imported outside the login firewall: the signature is the authentication.
 *
 * A `POST` — the one-click unsubscription of RFC 8058 a mail client sends with `List-Unsubscribe=One-Click`, or the
 * button of the page — stops the recipient's digest at once, without a CSRF token or a confirmation, and answers
 * `200`. A `GET` changes nothing: a link scanner or a preview may follow it; it shows the page with that button. A link
 * not signed, altered or expired gets a `403`.
 *
 * The page is `@PhilNotifications/digest/unsubscribe.html.twig`, given `state` (`confirm`, `done` or `refused`) and
 * `action`, the signed address the button posts to; without Twig, a line of text.
 */
final readonly class DigestUnsubscribeController
{
    public const string TEMPLATE = '@PhilNotifications/digest/unsubscribe.html.twig';

    public function __construct(
        private UriSigner $signer,
        private DigestUnsubscriber $unsubscriber,
        private TranslatorInterface $translator,
        private ?Environment $twig = null,
        private string $template = self::TEMPLATE,
    ) {
    }

    public function __invoke(Request $request, string $recipient): Response
    {
        if (!$this->signer->checkRequest($request)) {
            return $this->page('refused', '', Response::HTTP_FORBIDDEN);
        }

        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->page('confirm', $request->getUri(), Response::HTTP_OK);
        }

        $this->unsubscriber->unsubscribe($recipient);

        return $this->page('done', '', Response::HTTP_OK);
    }

    private function page(string $state, string $action, int $status): Response
    {
        $response = $this->twig instanceof Environment
            ? new Response($this->twig->render($this->template, ['state' => $state, 'action' => $action]), $status)
            : new Response($this->translator->trans('digest.unsubscribe.'.$state, [], NotificationType::DOMAIN), $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Robots-Tag', 'noindex');

        return $response;
    }
}
