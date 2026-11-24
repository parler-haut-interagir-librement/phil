<?php

declare(strict_types=1);

namespace Phil\SamlSp\Binding;

/**
 * How the browser carries a message: on HTTP-Redirect, the address to redirect to; on HTTP-POST, the action of the
 * form and its fields, which the page posts.
 */
final readonly class Outbound
{
    /**
     * @param array<string, string> $fields the fields of the form, empty on HTTP-Redirect
     */
    public function __construct(
        public Binding $binding,
        public string $url,
        public array $fields = [],
    ) {
    }

    public function isRedirect(): bool
    {
        return Binding::Redirect === $this->binding;
    }
}
