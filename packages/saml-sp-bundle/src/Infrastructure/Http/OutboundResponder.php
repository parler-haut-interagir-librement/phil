<?php

declare(strict_types=1);

namespace Phil\SamlSpBundle\Infrastructure\Http;

use Phil\SamlSp\Binding\Outbound;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns how a message travels into the answer of the browser: a redirect for HTTP-Redirect; for HTTP-POST, a page whose
 * form carries the fields to the identity provider, posted by the Stimulus controller `phil--saml-sp-bundle--auto-submit`
 * when the application runs Stimulus, by its button otherwise. Neither is cached, and the page runs no inline script.
 */
final class OutboundResponder
{
    private function __construct()
    {
    }

    public static function respond(Outbound $outbound): Response
    {
        if ($outbound->isRedirect()) {
            $response = new RedirectResponse($outbound->url, Response::HTTP_FOUND);
        } else {
            $fields = '';

            foreach ($outbound->fields as $name => $value) {
                $fields .= \sprintf('<input type="hidden" name="%s" value="%s">', self::escape($name), self::escape($value));
            }

            $response = new Response(
                '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Signing in</title></head><body>'
                .\sprintf('<form method="post" action="%s" data-controller="phil--saml-sp-bundle--auto-submit">%s', self::escape($outbound->url), $fields)
                .'<p>Your browser is taking you to your organisation to sign in.</p><button type="submit">Continue</button></form></body></html>',
                Response::HTTP_OK,
                ['Content-Type' => 'text/html; charset=UTF-8'],
            );
        }

        $response->headers->set('Cache-Control', 'no-store');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE | \ENT_HTML5, 'UTF-8');
    }
}
