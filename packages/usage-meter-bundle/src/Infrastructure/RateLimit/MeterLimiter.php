<?php

declare(strict_types=1);

namespace Phil\UsageMeterBundle\Infrastructure\RateLimit;

use Override;
use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\UsageMeter\Exception\QuotaExceeded;
use Phil\UsageMeter\Usage;
use Phil\UsageMeter\UsageMeter;
use Psr\Clock\ClockInterface;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * A limiter of `phil/rate-limit-bundle` that counts on a meter: the key of the request is the meter's subject, so that
 * the limits of the limit providers and of the subscription features apply — a paying account may be allowed more
 * requests than an anonymous address —, and a calendar period or a billing cycle limits requests as a window does.
 *
 * The reset is the seconds until the meter's window frees some of its use; a meter without a limit for the subject
 * writes no header.
 *
 * With `idempotency_key`, the requests that carry the same `Idempotency-Key` header count once — a client retrying a
 * request it sent, after a timeout, counts it once. Only a method that changes something — POST, PUT, PATCH, DELETE —
 * is keyed: a GET is counted every time, whatever it carries. The key the store keeps is an HMAC-SHA-256, under the
 * secret, of the limiter, the method, the URI, the body and the header's value: the same key on another request is
 * another key, so a client cannot spend one key on many actions. A header that is not 1 to 255 printable ASCII
 * characters, quoted or not, is answered 400 Bad Request.
 */
final readonly class MeterLimiter implements Limiter
{
    /** The request header a client names a request by, so that its retries count once. */
    public const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function __construct(
        private UsageMeter $meter,
        private ClockInterface $clock,
        /** The limiter's name, which the idempotency keys are bound to. */
        private string $name,
        /** The meter it counts on. */
        private string $meterName,
        private bool $idempotencyKey = false,
        #[SensitiveParameter]
        private string $secret = '',
    ) {
    }

    #[Override]
    public function consume(string $key, int $amount, Request $request): Decision
    {
        $header = $this->idempotencyKey ? $this->idempotencyHeader($request) : null;

        try {
            $usage = $this->meter->consume($key, $this->meterName, $amount, null === $header ? null : $this->keyOf($request, $header));
        } catch (QuotaExceeded $quotaExceeded) {
            $seconds = $this->secondsUntil($quotaExceeded->usage);

            return Decision::refused($quotaExceeded->usage->limit->value, $quotaExceeded->usage->remaining() ?? 0, $seconds, $seconds);
        }

        return Decision::accepted($usage->limit->value, $usage->remaining() ?? 0, $this->secondsUntil($usage));
    }

    /**
     * The value of the request's `Idempotency-Key` header, without its quotes; null on a safe method or without the
     * header.
     */
    private function idempotencyHeader(Request $request): ?string
    {
        $value = $request->headers->get(self::IDEMPOTENCY_HEADER);

        if (null === $value || $request->isMethodSafe()) {
            return null;
        }

        $key = 1 === preg_match('/^"(.*)"$/D', $value, $quoted) ? $quoted[1] : $value;

        if (1 !== preg_match('/^[\x20\x21\x23-\x5b\x5d-\x7e]{1,255}$/D', $key)) {
            throw new BadRequestHttpException(\sprintf('The %s header is 1 to 255 printable ASCII characters, without quotes or backslashes inside.', self::IDEMPOTENCY_HEADER));
        }

        return $key;
    }

    /**
     * The key the store keeps: bound to the limiter and to the request, so that it counts this request once and no
     * other.
     */
    private function keyOf(Request $request, string $header): string
    {
        return hash_hmac('sha256', implode("\n", [$this->name, $request->getMethod(), $request->getRequestUri(), hash('sha256', $request->getContent()), $header]), $this->secret);
    }

    /**
     * The seconds from now until the window frees some of its use, never below zero.
     */
    private function secondsUntil(Usage $usage): int
    {
        return max(0, $usage->resetsAt->getTimestamp() - $this->clock->now()->getTimestamp());
    }
}
