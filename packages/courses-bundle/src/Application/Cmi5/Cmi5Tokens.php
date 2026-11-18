<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Cmi5;

use Phil\CoursesBundle\Domain\Contract\LaunchSessionRepository;
use Phil\CoursesBundle\Domain\Model\LaunchSession;
use Phil\CoursesBundle\Domain\ValueObject\LaunchSessionId;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use SodiumException;
use Throwable;

/**
 * The tokens of the cmi5 sessions: the fetch URL gives a session's token once, as cmi5 asks; the assignable unit then
 * sends it as HTTP Basic credentials with each request. The token is the session's identifier and an HMAC of it under
 * the application's secret, so that nothing secret is kept in the database; it opens its session while the session is
 * neither ended nor expired.
 */
final readonly class Cmi5Tokens
{
    public const string ALREADY_USED = '1';

    public const string INVALID = '2';

    public const string GENERAL = '3';

    public function __construct(
        private LaunchSessionRepository $sessions,
        private ClockInterface $clock,
        #[SensitiveParameter]
        private string $secret,
    ) {
    }

    /**
     * The answer of the fetch URL: the token the first time, an error of cmi5 after.
     *
     * @return array{auth-token: string}|array{error-code: string, error-text: string}
     */
    public function fetch(string $session): array
    {
        try {
            $found = $this->sessions->find(LaunchSessionId::of($session));
        } catch (Throwable) {
            $found = null;
        }

        if (!$found instanceof LaunchSession || !$found->isUsable($this->clock->now())) {
            return ['error-code' => self::INVALID, 'error-text' => 'The session is unknown, ended or expired.'];
        }

        if (!$found->fetch()) {
            return ['error-code' => self::ALREADY_USED, 'error-text' => 'The token of this session was fetched already.'];
        }

        $this->sessions->save($found);

        return ['auth-token' => $this->token($found->id)];
    }

    /**
     * The session the `Authorization` header opens, or null.
     */
    public function authenticate(?string $authorization): ?LaunchSession
    {
        if (null === $authorization || 1 !== preg_match('/^Basic\s+([A-Za-z0-9+\/=]{1,200})$/', $authorization, $match)) {
            return null;
        }

        try {
            $credentials = sodium_base642bin($match[1], \SODIUM_BASE64_VARIANT_ORIGINAL);
        } catch (SodiumException) {
            return null;
        }

        if (1 !== preg_match('/^([^:]{1,36}):(.+)$/D', $credentials, $parts)) {
            return null;
        }

        try {
            $id = LaunchSessionId::of($parts[1]);
        } catch (Throwable) {
            return null;
        }

        if (!hash_equals($this->signature($id), $parts[2])) {
            return null;
        }

        $session = $this->sessions->find($id);

        return $session instanceof LaunchSession && $session->fetched && $session->isUsable($this->clock->now()) ? $session : null;
    }

    public function token(LaunchSessionId $session): string
    {
        return sodium_bin2base64($session->value.':'.$this->signature($session), \SODIUM_BASE64_VARIANT_ORIGINAL);
    }

    private function signature(LaunchSessionId $session): string
    {
        return hash_hmac('sha256', 'phil_courses.cmi5|'.$session->value, $this->secret);
    }
}
