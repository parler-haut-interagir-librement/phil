<?php

declare(strict_types=1);

namespace Phil\FormsBundle\Infrastructure\Mailer;

use Override;
use Phil\FormsBundle\Application\EntryNotifier;
use Phil\FormsBundle\Application\NotificationFailed;
use Phil\FormsBundle\Domain\Contract\ConditionEvaluator;
use Phil\FormsBundle\Domain\Field\FieldType;
use Phil\FormsBundle\Domain\Model\Entry;
use Phil\FormsBundle\Domain\ValueObject\Field;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * Sends an entry to its form's recipients, then to the addresses of each of its other notifications whose condition
 * holds, with that notification's subject; from the site's own address — a visitor's address in `From` would fail
 * the domain's SPF and DMARC — with the first email field in `Reply-To`; never to the visitor, which would make the
 * form a relay for spam. The email lists the fields asked, the value of a sensitive field withheld. Every email is
 * tried; when one fails, the first failure is thrown once the others are sent.
 *
 * Each email goes through Symfony Mailer's transports, rendered and sent in the current process, never through
 * `MailerInterface`: an application that routes `SendEmailMessage` to a Messenger transport would otherwise queue the
 * email unrendered, with the entry, its form and every value in its context, where the transport and its failure
 * transport keep them.
 */
final readonly class EmailNotifier implements EntryNotifier
{
    public const string HTML_TEMPLATE = '@PhilForms/email/entry.html.twig';

    public const string TEXT_TEMPLATE = '@PhilForms/email/entry.txt.twig';

    public function __construct(
        private TransportInterface $transport,
        private string $sender,
        private string $subjectPrefix = '',
        private ?ConditionEvaluator $logic = null,
    ) {
    }

    #[Override]
    public function notify(Entry $entry): void
    {
        $form = $entry->form;
        $emails = [] === $form->recipients() ? [] : [[$form->recipients(), $form->title()]];

        foreach ($form->notifications() as $notification) {
            if ($form->holds($notification->when, $entry->values, $this->logic)) {
                $emails[] = [$notification->recipients, $notification->subjectFor($form->title(), $entry->values)];
            }
        }

        $failure = null;

        foreach ($emails as [$recipients, $subject]) {
            try {
                $this->transport->send($this->email($entry, $recipients, $subject));
            } catch (TransportExceptionInterface $transportException) {
                $failure ??= $transportException;
            }
        }

        if ($failure instanceof TransportExceptionInterface) {
            throw NotificationFailed::because('email', $failure);
        }
    }

    /**
     * @param list<string> $recipients
     */
    private function email(Entry $entry, array $recipients, string $subject): TemplatedEmail
    {
        $fields = array_values(array_filter($entry->form->fields(), static fn (Field $field): bool => \array_key_exists($field->name, $entry->values)));
        $email = new TemplatedEmail()
            ->from($this->sender)
            ->to(...$recipients)
            ->subject($this->subjectPrefix.$subject)
            ->htmlTemplate(self::HTML_TEMPLATE)
            ->textTemplate(self::TEXT_TEMPLATE)
            ->context(['entry' => $entry, 'fields' => $fields])
            ->locale($entry->locale);

        foreach ($fields as $field) {
            $value = $entry->values[$field->name] ?? null;

            if (FieldType::Email === $field->type && !$field->sensitive && \is_string($value)) {
                $email->replyTo($value);

                break;
            }
        }

        $email->getHeaders()->addTextHeader('X-Auto-Response-Suppress', 'OOF, AutoReply');

        return $email;
    }
}
