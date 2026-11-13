<?php

declare(strict_types=1);

namespace Phil\AccessLinks;

use LogicException;
use Phil\Url\Url;
use SensitiveParameter;

/**
 * A newly issued link: its invitation, its token and its address. The token and the address exist only here, to be
 * sent to the recipient; the store keeps the token's hash. The object refuses to be serialized and hides both from
 * `var_dump()`, so that a queue, a cache or a log does not keep them by accident.
 */
final readonly class IssuedLink
{
    public function __construct(
        public Invitation $invitation,
        #[SensitiveParameter]
        public string $token,
        public Url $url,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return ['invitation' => $this->invitation->id, 'token' => 'hidden', 'url' => 'hidden'];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('An issued link holds its token in clear: it is never serialized.');
    }
}
