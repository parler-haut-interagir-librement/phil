<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Http;

use Phil\OpenBadges\Baking\PngBaker;
use Phil\OpenBadges\Baking\SvgBaker;
use Phil\OpenBadges\Exception\InvalidImage;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * The verification page: a visitor pastes a credential — a token or a document — or gives a baked PNG or SVG image,
 * and optionally the e-mail address or URL of whom they expect it to be issued to; the page says whether the
 * credential holds, and why not — a `422` when it does not. It stores nothing and changes nothing, so it needs no
 * session; the answer is never cached, and the image or text given is read within `$maxUpload` bytes.
 */
final readonly class VerificationController
{
    public const string ROUTE = 'phil_open_badges_verify';

    public function __construct(
        private CredentialCheck $check,
        private Environment $twig,
        private string $layout,
        private int $maxUpload,
    ) {
    }

    public function page(Request $request): Response
    {
        if (!$request->isMethod('POST')) {
            return $this->render(null);
        }

        return $this->render($this->result($request));
    }

    /**
     * The check of the image given or, without one, of the credential pasted.
     */
    private function result(Request $request): CheckResult
    {
        $image = $request->files->get('image');
        $identity = $request->request->getString('identity');

        if ($image instanceof UploadedFile && \UPLOAD_ERR_NO_FILE !== $image->getError()) {
            if (!$image->isValid() || $image->getSize() > $this->maxUpload) {
                return CheckResult::refused(\sprintf('An image is at most %d bytes.', $this->maxUpload));
            }

            try {
                $bytes = $image->getContent();
                $credential = str_starts_with($bytes, "\x89PNG") ? PngBaker::extract($bytes) : SvgBaker::extract($bytes);
            } catch (InvalidImage $invalidImage) {
                return CheckResult::refused($invalidImage->getMessage());
            }

            return null === $credential ? CheckResult::refused('The image holds no baked credential.') : $this->check->check($credential, $identity);
        }

        $pasted = trim($request->request->getString('credential'));

        if ('' === $pasted) {
            return CheckResult::refused('Paste a credential or give a baked image.');
        }

        if (\strlen($pasted) > $this->maxUpload) {
            return CheckResult::refused(\sprintf('A credential is at most %d bytes.', $this->maxUpload));
        }

        return $this->check->check($pasted, $identity);
    }

    private function render(?CheckResult $result): Response
    {
        $status = !$result instanceof CheckResult || $result->holds() ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY;
        $response = new Response($this->twig->render('@PhilOpenBadges/verify.html.twig', ['layout' => $this->layout, 'result' => $result]), $status);
        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
