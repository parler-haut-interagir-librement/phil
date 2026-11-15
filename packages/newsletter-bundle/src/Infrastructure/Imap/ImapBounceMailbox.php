<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Infrastructure\Imap;

use Override;
use Phil\NewsletterBundle\Application\Contract\BounceMailbox;
use Phil\NewsletterBundle\Infrastructure\Imap\Exception\ImapFailed;
use SensitiveParameter;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The bounce mailbox on an IMAP server: the unread messages of a folder, each read without being marked, then marked
 * read — and deleted, when `$delete` is set — once handled. The connection lasts one reading; `reset()` closes one a
 * worker left open.
 */
final readonly class ImapBounceMailbox implements BounceMailbox, ResetInterface
{
    public function __construct(
        private ImapClient $client,
        private string $user,
        #[SensitiveParameter]
        private string $password,
        private string $folder = 'INBOX',
        private bool $delete = false,
    ) {
    }

    /**
     * A mailbox on `host:port` over implicit TLS, the certificate checked.
     */
    public static function overTls(string $host, int $port, string $user, #[SensitiveParameter] string $password, string $folder = 'INBOX', bool $delete = false, float $timeout = 15.0): self
    {
        return new self(new ImapClient(static function () use ($host, $port, $timeout) {
            $context = stream_context_create(self::tlsOptions($host));
            $stream = @stream_socket_client(\sprintf('tls://%s:%d', $host, $port), $code, $message, $timeout, \STREAM_CLIENT_CONNECT, $context);

            if (false === $stream) {
                throw ImapFailed::because(\sprintf('The IMAP server %s:%d cannot be reached: %s', $host, $port, $message));
            }

            stream_set_timeout($stream, (int) ceil($timeout));

            return $stream;
        }), $user, $password, $folder, $delete);
    }

    /**
     * The options of the TLS stream to a host: its certificate and its name are both checked.
     *
     * @return array{ssl: array{verify_peer: true, verify_peer_name: true, peer_name: string}}
     */
    public static function tlsOptions(string $host): array
    {
        return ['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]];
    }

    #[Override]
    public function unread(int $limit): iterable
    {
        $this->client->open();

        try {
            $this->client->login($this->user, $this->password);
            $this->client->select($this->folder);

            foreach (\array_slice($this->client->unseen(), 0, max(1, $limit)) as $uid) {
                yield (string) $uid => $this->client->fetch($uid);
            }

            if ($this->delete) {
                $this->client->expunge();
            }
        } finally {
            $this->client->close();
        }
    }

    #[Override]
    public function acknowledge(string $id): void
    {
        if (!$this->client->isOpen() || 1 !== preg_match('/^[1-9][0-9]{0,9}$/D', $id)) {
            return;
        }

        $this->client->flag((int) $id, ...($this->delete ? ['\\Seen', '\\Deleted'] : ['\\Seen']));
    }

    #[Override]
    public function reset(): void
    {
        $this->client->close();
    }
}
