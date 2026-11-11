<?php

declare(strict_types=1);

namespace Phil\ContactFormBundle\Infrastructure\Mailer;

use Phil\ContactFormBundle\Application\ContactMessage;
use Phil\ContactFormBundle\Application\Recipients;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * Sends a message to its recipients. It is sent from the site's own address — a visitor's address in `From` would
 * fail the domain's SPF and DMARC — with the visitor in `Reply-To`, so that answering answers them; never to the
 * visitor, which would make the form a relay for spam. Out-of-office replies are asked not to come back.
 */
final readonly class ContactMailer
{
    public const string HTML_TEMPLATE = '@PhilContactForm/email/message.html.twig';

    public const string TEXT_TEMPLATE = '@PhilContactForm/email/message.txt.twig';

    public function __construct(
        private MailerInterface $mailer,
        private Recipients $recipients,
        private string $sender,
        private string $subjectPrefix = '',
    ) {
    }

    public function send(ContactMessage $message, ?string $locale = null): void
    {
        $subject = $this->line($message->subject ?? '');
        $email = new TemplatedEmail()
            ->from($this->sender)
            ->to(...$this->recipients->for($message))
            ->replyTo(new Address((string) $message->email, $this->line((string) $message->name)))
            ->subject($this->subjectPrefix.('' === $subject ? $this->line((string) $message->name) : $subject))
            ->htmlTemplate(self::HTML_TEMPLATE)
            ->textTemplate(self::TEXT_TEMPLATE)
            ->context(['contact' => $message])
            ->locale($locale);

        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');
        $this->mailer->send($email);
    }

    /**
     * A text for one header line: no line breaks, whatever the form was sent.
     */
    private function line(string $text): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $text) ?? '');
    }
}
