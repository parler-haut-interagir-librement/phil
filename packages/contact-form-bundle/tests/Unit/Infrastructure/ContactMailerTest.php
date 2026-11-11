<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Tests\Unit\Infrastructure;

use Phil\ContactFormBundle\Application\ConfiguredRecipients;
use Phil\ContactFormBundle\Application\ContactMessage;
use Phil\ContactFormBundle\Infrastructure\Mailer\ContactMailer;
use Phil\ContactFormBundle\Tests\Unit\Fixtures\RecordingMailer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

#[CoversClass(ContactMailer::class)]
#[CoversClass(ConfiguredRecipients::class)]
final class ContactMailerTest extends TestCase
{
    private function message(?string $subject): ContactMessage
    {
        $message = new ContactMessage();
        $message->name = "Ada\r\nBcc: spam@example.com";
        $message->email = 'ada@example.com';
        $message->subject = $subject;
        $message->message = 'A quote for twelve copies, please.';

        return $message;
    }

    public function testAMessageIsSentFromTheSiteWithTheVisitorToReplyTo(): void
    {
        $mailer = new RecordingMailer();
        $message = $this->message(" A quote\nplease ");
        new ContactMailer($mailer, new ConfiguredRecipients(['info@example.com', 'shop@example.com']), 'contact@example.com', '[Contact] ')->send($message, 'fr');

        $email = $mailer->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame(['contact@example.com'], array_map(static fn (Address $address): string => $address->getAddress(), $email->getFrom()));
        self::assertSame(['info@example.com', 'shop@example.com'], array_map(static fn (Address $address): string => $address->getAddress(), $email->getTo()));
        self::assertSame([['ada@example.com', 'Ada Bcc: spam@example.com']], array_map(static fn (Address $address): array => [$address->getAddress(), $address->getName()], $email->getReplyTo()));
        self::assertSame('[Contact] A quote please', $email->getSubject());
        self::assertSame([ContactMailer::HTML_TEMPLATE, ContactMailer::TEXT_TEMPLATE, ['contact' => $message], 'fr'], [$email->getHtmlTemplate(), $email->getTextTemplate(), $email->getContext(), $email->getLocale()]);
        self::assertSame('OOF, AutoReply', $email->getHeaders()->getHeaderBody('X-Auto-Response-Suppress'));
        self::assertSame([], $email->getBcc());
    }

    public function testWithoutSubjectTheSubjectIsTheName(): void
    {
        $mailer = new RecordingMailer();
        new ContactMailer($mailer, new ConfiguredRecipients(['info@example.com']), 'contact@example.com')->send($this->message('  '));

        $email = $mailer->sent[0];
        self::assertInstanceOf(TemplatedEmail::class, $email);
        self::assertSame(['Ada Bcc: spam@example.com', null], [$email->getSubject(), $email->getLocale()]);

        new ContactMailer($mailer, new ConfiguredRecipients(['info@example.com']), 'contact@example.com')->send($this->message(null));
        self::assertInstanceOf(TemplatedEmail::class, $mailer->sent[1]);
        self::assertSame('Ada Bcc: spam@example.com', $mailer->sent[1]->getSubject());
    }
}
