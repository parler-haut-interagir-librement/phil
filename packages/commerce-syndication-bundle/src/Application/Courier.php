<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Application;

use DateTimeImmutable;
use Phil\CommerceSyndicationBundle\Application\Port\MessageTransport;
use Phil\CommerceSyndicationBundle\Domain\Model\Partner;
use Phil\CommerceSyndicationBundle\Domain\Repository\MessageRepository;
use Phil\CommerceSyndicationBundle\Domain\Repository\PartnerRepository;
use Phil\StandardWebhooks\Ed25519SecretKey;
use Phil\StandardWebhooks\Signer;
use Phil\StandardWebhooks\SigningKey;
use Phil\StandardWebhooks\WebhookId;
use Phil\StandardWebhooks\WebhookTimestamp;
use SensitiveParameter;

/**
 * Delivers the outbox: each message due is signed with this application's key and posted to its partner.
 *
 * - A 2xx answer delivers it.
 * - A 4xx answer other than 404, 408, 425 and 429 gives it up at once: the partner refused it, and the same message
 *   sent again would be refused again.
 * - Any other answer, or none, schedules the next attempt, until the last allowed one; and the partner's other
 *   messages wait for the next run, so that a partner down costs one timeout per run, not one per message.
 *
 * Each message is claimed before it is sent: two runs at once — a run slower than the scheduler's minute — never send
 * the same message, and a run that dies leaves its claims to lapse.
 */
final readonly class Courier
{
    /** How long a run holds a message it claimed: longer than any run, short enough to retry after a crash. */
    public const string CLAIM = '+30 minutes';

    /** The 4xx answers that may change if the message is sent again later. */
    private const array TRANSIENT = [404, 408, 425, 429];

    private SigningKey $key;

    /**
     * @param string $signingKey this application's `whsk_` Ed25519 secret key
     */
    public function __construct(
        private MessageRepository $messages,
        private PartnerRepository $partners,
        private MessageTransport $transport,
        #[SensitiveParameter]
        string $signingKey,
        private int $maxAttempts,
        private Signer $signer = new Signer(),
    ) {
        $this->key = Ed25519SecretKey::fromString($signingKey);
    }

    /**
     * @return int how many messages were delivered
     */
    public function deliver(DateTimeImmutable $at, int $limit = 100): int
    {
        $delivered = 0;
        $unreachable = [];

        foreach ($this->messages->due($at, $limit) as $message) {
            if (\in_array($message->partner, $unreachable, true) || !$this->messages->claim($message, $at, $at->modify(self::CLAIM))) {
                continue;
            }

            $partner = $this->partners->findPartner($message->partner);

            if (!$partner instanceof Partner || !$partner->isActive()) {
                $message->failed($at, 'The partner is unknown or suspended.', $this->maxAttempts);

                continue;
            }

            $body = $message->body();
            $headers = $this->signer->sign(WebhookId::fromString($message->id), WebhookTimestamp::fromDateTime($at), $body, $this->key)->toArray();
            $status = $this->transport->send($partner->endpoint(), ['content-type' => 'application/json'] + $headers, $body);

            if ($status >= 200 && $status < 300) {
                $message->delivered($at);
                ++$delivered;

                continue;
            }

            $error = 0 === $status ? 'No answer.' : \sprintf('HTTP %d.', $status);

            if ($status >= 400 && $status < 500 && !\in_array($status, self::TRANSIENT, true)) {
                $message->refused($at, $error.' Refused by the partner: given up.');

                continue;
            }

            $message->failed($at, $error, $this->maxAttempts);
            $unreachable[] = $message->partner;
        }

        return $delivered;
    }
}
