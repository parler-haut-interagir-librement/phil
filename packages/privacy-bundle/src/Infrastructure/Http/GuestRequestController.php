<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\EmailAddress\Exception\InvalidEmailAddress;
use Phil\PrivacyBundle\Application\Exception\ErasureIncomplete;
use Phil\PrivacyBundle\Application\Exception\TooManyGuestRequests;
use Phil\PrivacyBundle\Application\GuestRequest\GuestRequests;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequestKind;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The access and erasure requests of people without an account: a form that sends a link, and the page that link opens.
 *
 * The form answers the same page whether the address is known or not. The page of the link acts only through its own
 * form, so that a mail scanner that follows the link changes nothing, and it sends no `Referer`: the link carries its
 * secret. Every page is `404` while `guest_requests.enabled` is false.
 */
final readonly class GuestRequestController
{
    public function __construct(
        private PrivacyPages $pages,
        private ?GuestRequests $guests,
    ) {
    }

    public function request(Request $request): Response
    {
        $guests = $this->guests();

        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->pages->render('guest/request', ['error' => null]);
        }

        $this->pages->guardForm($request);
        $kind = GuestRequestKind::tryFrom($request->request->getString('kind')) ?? throw new BadRequestHttpException('The kind of request is "access" or "erasure".');

        try {
            $guests->submit($kind, $request->request->getString('email'), $request->getClientIp() ?? 'unknown');
        } catch (TooManyGuestRequests $tooMany) {
            $response = $this->pages->render('guest/request', ['error' => 'guest.error.too_many'], Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', (string) $tooMany->retryAfter());

            return $response;
        } catch (InvalidEmailAddress) {
            return $this->pages->render('guest/request', ['error' => 'guest.error.address'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->pages->render('guest/sent');
    }

    public function confirm(Request $request, string $id): Response
    {
        $guests = $this->guests();

        if (!$request->isMethod(Request::METHOD_POST)) {
            $token = $request->query->getString('token');
            $pending = $guests->open($id, $token);

            return $this->private($pending instanceof GuestRequest
                ? $this->pages->render('guest/confirm', ['id' => $id, 'token' => $token, 'kind' => $pending->kind->value, 'incomplete' => false])
                : $this->invalid());
        }

        $this->pages->guardForm($request);
        $token = $request->request->getString('token');

        if (GuestRequestKind::Access->value === $request->request->getString('kind')) {
            $zip = $guests->access($id, $token);

            return $this->private(null === $zip ? $this->invalid() : PrivacyPages::archive($zip));
        }

        try {
            $reports = $guests->erase($id, $token);
        } catch (ErasureIncomplete) {
            return $this->private($this->pages->render('guest/confirm', ['id' => $id, 'token' => $token, 'kind' => GuestRequestKind::Erasure->value, 'incomplete' => true], Response::HTTP_SERVICE_UNAVAILABLE));
        }

        return $this->private(null === $reports ? $this->invalid() : $this->pages->render('guest/erased'));
    }

    private function guests(): GuestRequests
    {
        return $this->guests ?? throw new NotFoundHttpException();
    }

    private function invalid(): Response
    {
        return $this->pages->render('guest/invalid', [], Response::HTTP_NOT_FOUND);
    }

    private function private(Response $response): Response
    {
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
