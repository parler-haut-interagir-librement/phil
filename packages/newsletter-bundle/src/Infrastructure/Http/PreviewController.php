<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Http;

use Phil\NewsletterBundle\Application\Content\ContentRenderer;
use Phil\NewsletterBundle\Application\Contract\CurrentSite;
use Phil\NewsletterBundle\Domain\Contract\TemplateRepository;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The preview of the Markdown editor: the body an editor is writing, laid out as a message — by the house layout or a
 * template of the site —, for the role the configuration names. The answer is sandboxed by its Content Security Policy
 * and shown in a sandboxed frame.
 */
final readonly class PreviewController
{
    public const int MAX_BYTES = 262_144;

    public function __construct(
        private ContentRenderer $renderer,
        private TemplateRepository $templates,
        private CurrentSite $site,
        private AuthorizationCheckerInterface $authorization,
        private string $role,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->authorization->isGranted($this->role)) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        $markdown = (string) $request->request->get('markdown', '');
        $key = (string) $request->request->get('template', '');

        if (\strlen($markdown) > self::MAX_BYTES) {
            return new Response('', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $html = $this->renderer->campaign(
                (string) $request->request->get('subject', ''),
                (string) $request->request->get('preheader', ''),
                $markdown,
                '' === $key ? null : $this->templates->find($this->site->site(), $key),
                ['site' => $this->site->site()->key, 'unsubscribe_url' => '#', 'preferences_url' => '#'],
            )->html;
        } catch (InvalidNewsletterValue $invalidNewsletterValue) {
            return new Response($invalidNewsletterValue->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        return new Response($html, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "sandbox; default-src 'none'; img-src https: data:; style-src 'unsafe-inline'",
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
