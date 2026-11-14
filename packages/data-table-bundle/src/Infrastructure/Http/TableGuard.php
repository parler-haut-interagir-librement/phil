<?php

declare(strict_types=1);

namespace Phil\DataTableBundle\Infrastructure\Http;

use JsonException;
use Phil\DataTableBundle\Application\Exception\AccessDenied;
use SensitiveParameter;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * What a change has to pass before it runs: Symfony's stateless CSRF check of the token `phil_data_table`, the
 * browser's `Sec-Fetch-Site`, `Origin` or `Referer` naming this site or a token sent twice; and, for a plain form, the
 * seal of the context it carries, so that a browser cannot change the table it acts on.
 */
final readonly class TableGuard
{
    /** The CSRF token identifier, listed among the stateless ones of FrameworkBundle by the bundle. */
    public const string TOKEN_ID = 'phil_data_table';

    public function __construct(
        private CsrfTokenManagerInterface $tokens,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    public function token(): string
    {
        return $this->tokens->getToken(self::TOKEN_ID)->getValue();
    }

    /**
     * @throws AccessDenied when the check does not pass
     */
    public function assertTrusted(string $token): void
    {
        if (!$this->tokens->isTokenValid(new CsrfToken(self::TOKEN_ID, $token))) {
            throw AccessDenied::untrustedRequest();
        }
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public function encode(array $context): string
    {
        ksort($context);

        return json_encode($context, \JSON_THROW_ON_ERROR);
    }

    public function seal(string $table, string $context): string
    {
        return hash_hmac('sha256', $table."\0".$context, $this->secret);
    }

    /**
     * The context a form carries.
     *
     * @return array<string, scalar|null>
     *
     * @throws AccessDenied when its seal is not this site's
     */
    public function opened(string $table, string $context, string $seal): array
    {
        if (!hash_equals($this->seal($table, $context), $seal)) {
            throw AccessDenied::untrustedRequest();
        }

        try {
            /** @var array<string, scalar|null> $decoded */
            $decoded = json_decode($context, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw AccessDenied::untrustedRequest();
        }

        return $decoded;
    }
}
