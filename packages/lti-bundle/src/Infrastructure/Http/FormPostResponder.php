<?php

declare(strict_types=1);

namespace Phil\LtiBundle\Infrastructure\Http;

use Phil\Lti\Http\FormPost;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

/**
 * Answers with a page whose form the browser posts to the other side — an `id_token`, a deep linking response —: the
 * form submits itself through a short inline script, allowed by the nonce of the page's own content security policy and
 * needing nothing of the application, and shows its button when scripts do not run. The page is never cached, never
 * framed by another origin than the one it posts to, and sends no referrer.
 *
 * The template receives the nonce as `csp_nonce`: a template of the application that adds its own scripts gives them
 * this nonce, since the policy allows no other script.
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
        $nonce = sodium_bin2base64(random_bytes(18), \SODIUM_BASE64_VARIANT_ORIGINAL);
        $origin = $post->action->scheme.'://'.$post->action->host.(null === $post->action->port ? '' : ':'.$post->action->port);

        return new Response($this->twig->render($this->template, ['action' => $post->action->toString(), 'fields' => $post->fields, 'csp_nonce' => $nonce]), $status, [
            'Cache-Control' => 'no-store',
            'Referrer-Policy' => 'no-referrer',
            'Content-Security-Policy' => \sprintf("script-src 'nonce-%s'; form-action %s; frame-ancestors 'self' %s", $nonce, $origin, $origin),
        ]);
    }
}
