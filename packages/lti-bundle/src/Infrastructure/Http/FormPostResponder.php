<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Http;

use Phil\Lti\Http\FormPost;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Answers with a page whose form the browser posts to the other side — an `id_token`, a deep linking response —: the
 * form submits itself through the bundle's Stimulus controller, and shows its button when scripts do not run. The page
 * is never cached, never framed by another origin than the one it posts to, and sends no referrer.
 */
final readonly class FormPostResponder
{
    public function __construct(
        private Environment $twig,
        private string $template = '@PhilLti/form_post.html.twig',
    ) {
    }

    public function respond(FormPost $post, int $status = Response::HTTP_OK): Response
    {
        return new Response($this->twig->render($this->template, ['action' => $post->action->toString(), 'fields' => $post->fields]), $status, [
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => \sprintf("form-action %s; frame-ancestors 'self' %s", $post->action->scheme.'://'.$post->action->host.(null === $post->action->port ? '' : ':'.$post->action->port), $post->action->scheme.'://'.$post->action->host.(null === $post->action->port ? '' : ':'.$post->action->port)),
        ]);
    }
}
