<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Infrastructure\Mailbox;

use Override;
use Phil\EmailAddressBundle\Application\Contract\BounceMailbox;
use Phil\EmailAddressBundle\Application\MailboxMessage;
use RuntimeException;

/**
 * A bounce mailbox the mail server delivers to as a Maildir: the messages of `new/` are fetched, the oldest first, and
 * an acknowledged one is moved to `cur/` with the « seen » flag, as a mail program does. Each message is read up to
 * the size given, past which the bounce parser would not read it anyway.
 */
final readonly class MaildirBounceMailbox implements BounceMailbox
{
    /** The most bytes read of a message by default: 1 MiB. */
    public const int MAX_BYTES = 1_048_576;

    /**
     * @param string       $directory the Maildir, holding `new/` and `cur/`
     * @param positive-int $maxBytes  the most bytes read of a message
     */
    public function __construct(
        private string $directory,
        private int $maxBytes = self::MAX_BYTES,
    ) {
    }

    /**
     * @throws RuntimeException when `new/` cannot be read
     */
    #[Override]
    public function fetch(int $limit): iterable
    {
        $new = $this->directory.'/new';
        $names = is_dir($new) ? scandir($new) : false;

        if (false === $names) {
            throw new RuntimeException(\sprintf('The Maildir "%s" has no readable new/ folder.', $this->directory));
        }

        $count = 0;

        foreach ($names as $name) {
            if ($count >= $limit) {
                return;
            }

            if (!$this->isMessageName($name) || !is_file($new.'/'.$name)) {
                continue;
            }

            $raw = @file_get_contents($new.'/'.$name, length: $this->maxBytes);

            if (false !== $raw) {
                ++$count;

                yield new MailboxMessage($name, $raw);
            }
        }
    }

    /**
     * @throws RuntimeException when the message cannot be moved
     */
    #[Override]
    public function acknowledge(MailboxMessage $message): void
    {
        if (!$this->isMessageName($message->id)) {
            throw new RuntimeException('A Maildir message is named without a slash, and not "." nor "..".');
        }

        $cur = $this->directory.'/cur';

        // mkdir() fails on a folder already there, made earlier or by another poller meanwhile.
        if (!@mkdir($cur, 0o700) && !is_dir($cur)) {
            throw new RuntimeException(\sprintf('The Maildir "%s" has no cur/ folder and none can be made.', $this->directory));
        }

        $name = explode(':', $message->id)[0];

        if (!@rename($this->directory.'/new/'.$message->id, $cur.'/'.$name.':2,S')) {
            throw new RuntimeException(\sprintf('The message "%s" cannot be moved to cur/.', $message->id));
        }
    }

    private function isMessageName(string $name): bool
    {
        return '' !== $name && '.' !== $name[0] && !str_contains($name, '/') && !str_contains($name, '\\');
    }
}
