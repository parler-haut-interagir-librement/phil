<?php

declare(strict_types=1);

namespace Phil\IdentityProviderGoogleBundle\Infrastructure\Http;

use Phil\IdentityProviderGoogleBundle\Application\Exception\TransmitterUnreachable;
use Phil\IdentityProviderGoogleBundle\Application\SecurityEventReceiver;
use Phil\IdentityProviderGoogleBundle\Application\SecurityEventTokenReader;
use Phil\IdentityProviderGoogleBundle\Domain\Exception\InvalidSecurityEventToken;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The push endpoint of security events (RFC 8935), `phil_identity_provider_google_risc`: the transmitter posts one
 * token, `application/secevent+jwt`, and is answered `202 Accepted` once it was acted on, a `400` with the error code
 * and a description otherwise, or a `503` when the keys could not be read, so that it sends the token again. It is
 * not found while the receiver is off.
 */
final readonly class RiscController
{
    public const string ROUTE = 'phil_identity_provider_google_risc';

    public function __construct(
        private ?SecurityEventReceiver $receiver = null,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->receiver instanceof SecurityEventReceiver) {
            throw new NotFoundHttpException('The receiver of security events is off.');
        }

        $type = strtolower(trim(explode(';', (string) $request->headers->get('Content-Type'))[0]));

        if (!\in_array($type, ['application/secevent+jwt', 'application/jwt'], true)) {
            return $this->error(InvalidSecurityEventToken::malformed('The body is not application/secevent+jwt.'));
        }

        $length = (int) $request->headers->get('Content-Length', '0');

        if ($length > SecurityEventTokenReader::MAX_LENGTH) {
            return $this->error(InvalidSecurityEventToken::malformed('The body is too long.'));
        }

        try {
            $this->receiver->receive(trim($request->getContent()));
        } catch (InvalidSecurityEventToken $invalid) {
            return $this->error($invalid);
        } catch (TransmitterUnreachable) {
            return new Response('', Response::HTTP_SERVICE_UNAVAILABLE, ['Retry-After' => '60', 'Cache-Control' => 'no-store']);
        }

        return new Response('', Response::HTTP_ACCEPTED, ['Cache-Control' => 'no-store']);
    }

    private function error(InvalidSecurityEventToken $invalid): JsonResponse
    {
        return new JsonResponse(['err' => $invalid->error, 'description' => $invalid->getMessage()], Response::HTTP_BAD_REQUEST, ['Cache-Control' => 'no-store']);
    }
}
