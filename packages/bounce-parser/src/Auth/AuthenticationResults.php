<?php

declare(strict_types=1);

namespace Phil\BounceParser\Auth;

use Override;
use Phil\BounceParser\Contract\ReportAuthenticator;
use Phil\BounceParser\FeedbackReport;
use Phil\BounceParser\Mime\Part;

/**
 * Vouches for a report whose message carries an `Authentication-Results` field (RFC 8601) written by the application's
 * own mail server — one of the authserv-ids given — that records a DKIM signature that passed for one of the domains of
 * the feedback loops, or a subdomain of one.
 *
 * The fields of other authserv-ids are ignored: anyone can write one. The mail server must remove, from the messages
 * it receives, every field that claims its own authserv-id, as RFC 8601 asks.
 */
final readonly class AuthenticationResults implements ReportAuthenticator
{
    /** @var array<string, true> */
    private array $authservIds;

    /** @var list<string> */
    private array $domains;

    /**
     * @param list<string> $authservIds the names the application's mail server writes its results under
     * @param list<string> $domains     the signing domains of the feedback loops
     */
    public function __construct(array $authservIds, array $domains)
    {
        $this->authservIds = array_fill_keys(array_map(strtolower(...), $authservIds), true);
        $this->domains = array_map(strtolower(...), $domains);
    }

    #[Override]
    public function isAuthentic(Part $message, FeedbackReport $report): bool
    {
        foreach ($message->headers->all('Authentication-Results') as $value) {
            // Comments may hold semicolons; they say nothing a result needs.
            $segments = explode(';', (string) preg_replace('/\([^()]*\)/', '', $value));
            $id = strtolower((string) strtok($segments[0], " \t"));

            if (!isset($this->authservIds[$id])) {
                continue;
            }

            // The first segment holds the authserv-id, never a result.
            foreach ($segments as $result) {
                if (1 === preg_match('/^\s*dkim\s*=\s*pass\b/i', $result)
                    && 1 === preg_match('/\bheader\.d\s*=\s*"?([^\s";]+)/i', $result, $match)
                    && $this->trusts(strtolower($match[1]))) {
                    return true;
                }
            }
        }

        return false;
    }

    private function trusts(string $domain): bool
    {
        return array_any($this->domains, static fn (string $trusted): bool => $domain === $trusted || str_ends_with($domain, '.'.$trusted));
    }
}
