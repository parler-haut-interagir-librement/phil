<?php

declare(strict_types=1);

namespace Phil\StandardWebhooksBundle\Infrastructure\Http;

use Phil\StandardWebhooks\Exception\WebhookException;
use Phil\StandardWebhooks\WebhookId;
use RuntimeException;
use Throwable;

/**
 * A delivery the receiver did not acknowledge: an answer outside `2xx`, none, or a URL the sender refused to send to.
 * `retryable` says whether retrying it later, with the same id, can succeed: after a `5xx` or no answer, not after a
 * `4xx` or a refused URL.
 *
 * The message is for the sender's operators: it names the status, or the transport error, which a URL given by a
 * third party must not get back — relayed whole, it would tell that party what answers inside the network. A
 * receiver's URL often carries its own secret in its path or query, and the HTTP client writes the URL into its
 * transport errors: the message keeps each URL as its origin alone (scheme, host and port), on one line of at most
 * 500 characters, and the transport error stays out of the exception chain, named by its class in `cause`, so
 * that neither a log nor a failed message stores the receiver's secret.
 */
final class DeliveryFailed extends RuntimeException implements WebhookException
{
    private function __construct(
        public readonly WebhookId $id,
        public readonly ?int $status,
        public readonly bool $retryable,
        string $message,
        /**
         * The class of the transport error behind a delivery that was not answered.
         *
         * @var class-string<Throwable>|null
         */
        public readonly ?string $cause = null,
    ) {
        parent::__construct($message);
    }

    public static function answered(WebhookId $id, int $status): self
    {
        return new self($id, $status, $status >= 500, \sprintf('The webhook %s was answered %d.', $id->value, $status));
    }

    public static function unanswered(WebhookId $id, Throwable $previous): self
    {
        return new self($id, null, true, \sprintf('The webhook %s was not answered: %s', $id->value, self::withoutAddresses($previous->getMessage())), $previous::class);
    }

    public static function refused(WebhookId $id, string $reason): self
    {
        return new self($id, null, false, \sprintf('The webhook %s was not sent: %s.', $id->value, $reason));
    }

    /**
     * The transport error with each URL cut to its origin, on one line of bounded length.
     */
    private static function withoutAddresses(string $error): string
    {
        $error = (string) preg_replace(
            ['/[\x00-\x1F\x7F]++/', '~\b([a-z][a-z0-9+.\-]*+://)(?:[^\s/?#@"\'<>]*+@)?([^\s/?#"\'<>]*+)[^\s"\'<>]*+~i'],
            [' ', '$1$2'],
            substr($error, 0, 2048),
        );

        return \strlen($error) > 500 ? substr($error, 0, 500) : $error;
    }
}
