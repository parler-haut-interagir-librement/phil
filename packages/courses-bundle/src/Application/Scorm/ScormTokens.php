<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Scorm;

use Phil\CoursesBundle\Domain\Exception\AttemptRefused;
use Phil\CoursesBundle\Domain\ValueObject\ScormAttemptId;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Throwable;

/**
 * The attempt tokens of the SCORM sessions: the attempt, the session and the moment the token expires, signed by an
 * HMAC under a key derived from the application's secret, so that nothing secret is kept in the database. The page of
 * the session sends it with each commit, and receives a new one in answer: a token holds `ttl` seconds after the last
 * commit.
 */
final readonly class ScormTokens
{
    public const int TTL = 14_400;

    private const int MAX_LENGTH = 200;

    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_courses.scorm_token';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public function __construct(
        private ClockInterface $clock,
        #[SensitiveParameter]
        string $secret,
        private int $ttl = self::TTL,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    public function issue(ScormAttemptId $attempt, int $session): string
    {
        $payload = $attempt->value.'.'.$session.'.'.($this->clock->now()->getTimestamp() + $this->ttl);

        return $payload.'.'.$this->signature($payload);
    }

    /**
     * The attempt and the session a token names.
     *
     * @return array{0: ScormAttemptId, 1: int}
     *
     * @throws AttemptRefused when the token is malformed, forged or expired
     */
    public function verify(string $token): array
    {
        if (\strlen($token) > self::MAX_LENGTH || 1 !== preg_match('/^([0-9a-f-]{36})\.(\d{1,9})\.(\d{1,12})\.([A-Za-z0-9_-]{43})$/D', $token, $match)) {
            throw AttemptRefused::because('The attempt token is not one this site gives.');
        }

        if (!hash_equals($this->signature($match[1].'.'.$match[2].'.'.$match[3]), $match[4])) {
            throw AttemptRefused::because('The attempt token is not one this site gives.');
        }

        if ((int) $match[3] < $this->clock->now()->getTimestamp()) {
            throw AttemptRefused::because('The attempt token expired: launch the activity again.');
        }

        try {
            return [ScormAttemptId::of($match[1]), (int) $match[2]];
        } catch (Throwable) {
            throw AttemptRefused::because('The attempt token is not one this site gives.');
        }
    }

    private function signature(string $payload): string
    {
        return sodium_bin2base64(hash_hmac('sha256', 'phil_courses.scorm|'.$payload, $this->key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
