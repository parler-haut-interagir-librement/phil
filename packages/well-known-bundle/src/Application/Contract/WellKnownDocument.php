<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application\Contract;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A document served at a well-known address: the built-in ones, and those other bundles and the application add with
 * `#[AsWellKnownDocument]` or the `phil_well_known.document` tag.
 *
 * The document answers with its body, its media type and the headers of its own, such as CORS. The controller adds
 * `X-Robots-Tag: noindex`, and to a successful answer that sets no `max-age` nor `no-store` the shared cache lifetime
 * and an `ETag`. A document starts no session and sets no cookie: its route is stateless.
 */
interface WellKnownDocument
{
    public function serve(Request $request): Response;
}
