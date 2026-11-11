<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Infrastructure\Http;

use Phil\QrCode\Exception\QrCodeException;
use Phil\QrCodeBundle\Application\QrCodes;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * `/qr-code.svg` and `/qr-code.png`: the code of a signed address, drawn with the defaults of the configuration and
 * kept by browsers and shared caches — the same address always gives the same image. An address the application did
 * not sign, or a code that cannot be drawn, is not found. The SVG is served with a policy that runs nothing.
 */
final readonly class QrCodeController
{
    public const string ROUTE = 'phil_qr_code';

    public function __construct(
        private QrCodes $codes,
        private UriSigner $signer,
        private int $maxAge = 31_536_000,
    ) {
    }

    public function __invoke(Request $request, string $format): Response
    {
        $query = $request->query->all();
        $data = $query['data'] ?? null;
        $logo = $query['logo'] ?? '';

        if (!$this->signer->checkRequest($request) || !\is_string($data) || !\is_string($logo)) {
            throw new NotFoundHttpException('No such QR code.');
        }

        $response = new Response();
        $response->setMaxAge($this->maxAge);
        $response->setSharedMaxAge($this->maxAge);
        $response->setImmutable();
        $response->setEtag(hash('xxh128', $format."\0".$logo."\0".$data));

        if ($response->isNotModified($request)) {
            return $response;
        }

        $options = '' === $logo ? [] : ['logo' => $logo];

        try {
            $body = 'png' === $format ? $this->codes->png($data, $options) : $this->codes->svg($data, $options);
        } catch (QrCodeException $qrCodeException) {
            throw new NotFoundHttpException('No such QR code.', $qrCodeException);
        }

        $response->setContent($body);
        $response->headers->set('Content-Type', 'png' === $format ? 'image/png' : 'image/svg+xml');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Content-Security-Policy', "default-src 'none'; img-src data:; style-src 'unsafe-inline'");

        return $response;
    }
}
