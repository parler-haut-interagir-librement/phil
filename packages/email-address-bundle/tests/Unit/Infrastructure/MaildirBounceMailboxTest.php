<?php

declare(strict_types=1);

namespace Phil\EmailAddressBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\EmailAddressBundle\Application\MailboxMessage;
use Phil\EmailAddressBundle\Infrastructure\Mailbox\MaildirBounceMailbox;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(MaildirBounceMailbox::class)]
final class MaildirBounceMailboxTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-email-address-maildir-'.bin2hex(random_bytes(6));
        mkdir($this->directory.'/new', 0o700, true);
    }

    #[Override]
    protected function tearDown(): void
    {
        foreach (['new', 'cur'] as $folder) {
            $files = glob($this->directory.'/'.$folder.'/{,.}*', \GLOB_BRACE);

            foreach (false === $files ? [] : $files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            if (is_dir($this->directory.'/'.$folder.'/sub')) {
                rmdir($this->directory.'/'.$folder.'/sub');
            }

            if (is_dir($this->directory.'/'.$folder)) {
                rmdir($this->directory.'/'.$folder);
            }
        }

        rmdir($this->directory);
    }

    public function testTheMessagesOfNewAreFetchedTheOldestFirstUpToTheLimit(): void
    {
        file_put_contents($this->directory.'/new/1700000002.b.host', 'second');
        file_put_contents($this->directory.'/new/1700000001.a.host', 'first');
        file_put_contents($this->directory.'/new/1700000003.c.host', 'third');
        file_put_contents($this->directory.'/new/.hidden', 'hidden');
        mkdir($this->directory.'/new/sub');

        $mailbox = new MaildirBounceMailbox($this->directory);

        self::assertEquals([new MailboxMessage('1700000001.a.host', 'first'), new MailboxMessage('1700000002.b.host', 'second')], iterator_to_array($mailbox->fetch(2), false));
        self::assertCount(3, iterator_to_array($mailbox->fetch(10), false));
    }

    public function testAMessageIsReadUpToTheSizeGiven(): void
    {
        file_put_contents($this->directory.'/new/1.a.host', 'abcdefghij');

        self::assertEquals([new MailboxMessage('1.a.host', 'abcd')], iterator_to_array(new MaildirBounceMailbox($this->directory, 4)->fetch(1), false));
    }

    public function testAMessageIsReadUpToOneMebibyteByDefault(): void
    {
        file_put_contents($this->directory.'/new/1.a.host', str_repeat('a', MaildirBounceMailbox::MAX_BYTES + 1));

        $messages = iterator_to_array(new MaildirBounceMailbox($this->directory)->fetch(1), false);

        self::assertSame(1_048_576, MaildirBounceMailbox::MAX_BYTES);
        self::assertCount(1, $messages);
        self::assertSame(MaildirBounceMailbox::MAX_BYTES, \strlen($messages[0]->raw));
    }

    public function testAnAcknowledgedMessageMovesToCurAsSeen(): void
    {
        file_put_contents($this->directory.'/new/1.a.host', 'first');
        $mailbox = new MaildirBounceMailbox($this->directory);

        $mailbox->acknowledge(new MailboxMessage('1.a.host', 'first'));

        self::assertFileDoesNotExist($this->directory.'/new/1.a.host');
        self::assertStringEqualsFile($this->directory.'/cur/1.a.host:2,S', 'first');
        self::assertSame(0o700, fileperms($this->directory.'/cur') & 0o777);
        self::assertSame([], iterator_to_array($mailbox->fetch(10), false));

        file_put_contents($this->directory.'/new/2.b.host:2,', 'second');
        $mailbox->acknowledge(new MailboxMessage('2.b.host:2,', 'second'));
        self::assertFileExists($this->directory.'/cur/2.b.host:2,S');
    }

    public function testAMaildirWithoutNewCannotBeRead(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('has no readable new/ folder.');

        iterator_to_array(new MaildirBounceMailbox($this->directory.'/missing')->fetch(1));
    }

    #[TestWith(['../escape'])]
    #[TestWith(['..'])]
    #[TestWith(['.hidden'])]
    #[TestWith(['a/b'])]
    #[TestWith(['a\\b'])]
    #[TestWith([''])]
    public function testOnlyAMessageNameIsAcknowledged(string $id): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('A Maildir message is named without a slash, and not "." nor "..".');

        new MaildirBounceMailbox($this->directory)->acknowledge(new MailboxMessage($id, ''));
    }

    public function testAMessageGoneCannotBeAcknowledged(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('The message "1.a.host" cannot be moved to cur/.');

        new MaildirBounceMailbox($this->directory)->acknowledge(new MailboxMessage('1.a.host', ''));
    }

    public function testCurIsMadeWhenMissingOrKeptWhenThere(): void
    {
        mkdir($this->directory.'/cur', 0o750);
        file_put_contents($this->directory.'/new/1.a.host', 'first');

        new MaildirBounceMailbox($this->directory)->acknowledge(new MailboxMessage('1.a.host', 'first'));

        self::assertSame(0o750, fileperms($this->directory.'/cur') & 0o777);
    }

    public function testCurThatCannotBeMadeIsTold(): void
    {
        file_put_contents($this->directory.'/cur', 'a file in its place');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIsOrContains('has no cur/ folder and none can be made.');

        try {
            new MaildirBounceMailbox($this->directory)->acknowledge(new MailboxMessage('1.a.host', ''));
        } finally {
            unlink($this->directory.'/cur');
        }
    }
}
