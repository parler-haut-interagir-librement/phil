<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Attribute;
use DateMalformedStringException;
use DateTimeImmutable;
use DateTimeZone;
use Phil\ApiContract\Deprecation\DeprecationSignal;
use Phil\ApiContract\Exception\InvalidContractValue;

/**
 * A controller that is deprecated, or will be removed: its responses carry `Deprecation`, `Sunset` and a `Link`
 * with the relation `deprecation`, as RFC 9745 and RFC 8594 say. Dates are ISO 8601, in UTC unless they say.
 *
 * The signal is built when the attribute is: a date that cannot be read, a sunset before the deprecation or a link
 * that cannot be sent is an `InvalidContractValue`, which the bundle raises when the container is compiled for
 * every controller service carrying the attribute, rather than on each request to the endpoint.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class DeprecatedEndpoint
{
    private DeprecationSignal $signal;

    public function __construct(
        public ?string $deprecatedAt = null,
        public ?string $sunsetAt = null,
        public ?string $link = null,
    ) {
        $this->signal = DeprecationSignal::of($this->date('deprecatedAt', $deprecatedAt), $this->date('sunsetAt', $sunsetAt), $link);
    }

    public function signal(): DeprecationSignal
    {
        return $this->signal;
    }

    private function date(string $name, ?string $date): ?DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        try {
            return new DateTimeImmutable($date, new DateTimeZone('UTC'));
        } catch (DateMalformedStringException $dateMalformedStringException) {
            throw new InvalidContractValue(\sprintf('The %s of #[DeprecatedEndpoint] is not a date: "%s".', $name, $date), $dateMalformedStringException->getCode(), previous: $dateMalformedStringException);
        }
    }
}
