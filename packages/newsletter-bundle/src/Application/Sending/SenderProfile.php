<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Sending;

use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;
use Phil\NewsletterBundle\Domain\ValueObject\SendWindow;

/**
 * Who a message comes from and how it goes out: the sender's address and name, the reply address, the one Mailer
 * transport it uses — never another, there is no rotation —, the hours it sends in, its daily ceiling, and the pace
 * per recipient domain.
 */
final readonly class SenderProfile
{
    public SendWindow $window;

    /**
     * @param array<string, int> $domainRates messages per minute to a recipient domain, `gmail.com` => 60; every other
     *                                        domain takes `$rate`, each counted on its own
     */
    public function __construct(
        public string $name,
        public string $from,
        public string $fromName = '',
        public ?string $replyTo = null,
        public ?string $transport = null,
        ?SendWindow $window = null,
        public ?int $dailyCap = null,
        public int $rate = 120,
        public array $domainRates = [],
        public ?string $unsubscribeMailbox = null,
    ) {
        $this->window = $window ?? SendWindow::always();

        if (1 !== preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/D', $name)) {
            throw InvalidNewsletterValue::because(\sprintf('"%s" is not a sender profile name.', $name));
        }

        if ($rate < 1 || (null !== $dailyCap && $dailyCap < 1) || [] !== array_filter($domainRates, static fn (int $limit): bool => $limit < 1)) {
            throw InvalidNewsletterValue::because(\sprintf('The rates and the daily ceiling of the sender profile "%s" are positive.', $name));
        }
    }

    /**
     * Messages per minute to a recipient domain.
     */
    public function rateFor(string $domain): int
    {
        return $this->domainRates[strtolower($domain)] ?? $this->rate;
    }
}
