<?php

declare(strict_types=1);

namespace Phil\Lti\Http;

use Phil\Url\Url;

/**
 * A form the browser posts to the other side, as LTI sends an `id_token` or a deep linking response: the address and
 * the fields, rendered by the application as a form that submits itself.
 */
final readonly class FormPost
{
    /**
     * @param array<string, string> $fields
     */
    public function __construct(
        public Url $action,
        public array $fields,
    ) {
    }
}
