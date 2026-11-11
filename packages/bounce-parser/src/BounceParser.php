<?php

declare(strict_types=1);

namespace Phil\BounceParser;

use Phil\BounceParser\Contract\ReportAuthenticator;
use Phil\BounceParser\Mime\Headers;
use Phil\BounceParser\Mime\MimeParser;
use Phil\BounceParser\Mime\Part;
use Phil\BounceParser\Reader\Addresses;
use Phil\BounceParser\Reader\AutoReplyReader;
use Phil\BounceParser\Reader\DeliveryStatusReader;
use Phil\BounceParser\Reader\FeedbackReader;
use Phil\BounceParser\Reader\PlainTextReader;
use Phil\BounceParser\Reader\Texts;
use Phil\BounceParser\Rule\Field;
use Phil\BounceParser\Rule\Rule;
use Phil\BounceParser\Rule\Rules;
use Phil\BounceParser\Rule\RuleScope;

/**
 * Reads a message received on a bounce mailbox and says what it is: a delivery status notification, an abuse report,
 * a bounce written as text, an automatic reply, or none of these; then the application's rules, in their order, may
 * decide otherwise.
 *
 * It never throws on a message, whatever it holds, and keeps no state between calls.
 */
final readonly class BounceParser
{
    /**
     * The fields the receiving mail server adds with the envelope recipient, read in this order for the VERP tag.
     * `To` is not among them: the sender writes it.
     */
    public const array ENVELOPE_FIELDS = ['Delivered-To', 'X-Original-To', 'Envelope-To'];

    private MimeParser $mime;

    private DeliveryStatusReader $deliveryStatus;

    private FeedbackReader $feedback;

    private PlainTextReader $plainText;

    /**
     * @param Verp|null                $verp           the return paths the messages were sent with, to read their tag
     * @param ReportAuthenticator|null $authenticator  what vouches for abuse reports; without one, each goes to review
     * @param list<string>             $envelopeFields the fields the VERP tag is read from, in order: only those the
     *                                                 application's own mail server adds
     */
    public function __construct(
        private Rules $rules = new Rules(),
        private ?Verp $verp = null,
        private ?ReportAuthenticator $authenticator = null,
        private Limits $limits = new Limits(),
        private array $envelopeFields = self::ENVELOPE_FIELDS,
    ) {
        $this->mime = new MimeParser($limits);
        $this->deliveryStatus = new DeliveryStatusReader($limits->maxHeaders);
        $this->feedback = new FeedbackReader($limits->maxHeaders);
        $this->plainText = new PlainTextReader($limits->maxText);
    }

    public function parse(string $raw): BounceReport
    {
        $message = $this->mime->parse($raw);
        $report = $this->read($message);

        foreach ($this->rules as $rule) {
            if ((RuleScope::Always === $rule->scope || !$report->isRecognised()) && $rule->matches($this->text($rule, $report))) {
                return $report->decidedBy($rule->name, $rule->kind, $rule->action);
            }
        }

        return $report;
    }

    private function read(Part $message): BounceReport
    {
        $original = $this->original($message);
        $tag = $this->tag($message->headers);
        $recipients = $this->deliveryStatus->read($message);

        if (null !== $recipients) {
            $kind = $this->worst($recipients);

            return new BounceReport(ReportFormat::DeliveryStatus, $kind, BounceAction::for($kind), $message, $recipients, original: $original, verpTag: $tag);
        }

        $feedback = $this->feedback->read($message);

        if ($feedback instanceof FeedbackReport) {
            return $this->complaint($message, $feedback, $original, $tag);
        }

        $recipients = $this->plainText->read($message);

        if (null !== $recipients) {
            $kind = $this->worst($recipients);

            return new BounceReport(ReportFormat::PlainText, $kind, BounceAction::for($kind), $message, $recipients, original: $original, verpTag: $tag);
        }

        if (AutoReplyReader::isAutoReply($message)) {
            $from = Addresses::ofHeader($message->headers->get('From'));
            $inReplyTo = $message->headers->get('In-Reply-To');

            return new BounceReport(
                ReportFormat::AutoReply,
                BounceKind::AutoReply,
                BounceAction::Ignore,
                $message,
                null === $from ? [] : [new Recipient($from, BounceKind::AutoReply)],
                original: $original ?? (null === $inReplyTo ? null : new OriginalMessage(new Headers([['Message-ID', $inReplyTo]]))),
                verpTag: $tag,
            );
        }

        return new BounceReport(ReportFormat::Unrecognised, BounceKind::Unknown, BounceAction::Review, $message, original: $original, verpTag: $tag);
    }

    private function complaint(Part $message, FeedbackReport $feedback, ?OriginalMessage $original, ?string $tag): BounceReport
    {
        $authentic = true === $this->authenticator?->isAuthentic($message, $feedback);
        $kind = $feedback->type->isComplaint() ? BounceKind::Complaint : BounceKind::Feedback;
        $action = match (true) {
            FeedbackType::NotSpam === $feedback->type => BounceAction::Ignore,
            BounceKind::Complaint === $kind && $authentic => BounceAction::Unsubscribe,
            default => BounceAction::Review,
        };
        $to = Addresses::ofHeader($original?->header('To'));
        $addresses = [] === $feedback->originalRcptTo && null !== $to ? [$to] : $feedback->originalRcptTo;
        $recipients = array_map(static fn (string $address): Recipient => new Recipient($address, $kind), $addresses);

        return new BounceReport(ReportFormat::FeedbackReport, $kind, $action, $message, $recipients, $feedback, $original, $tag, authentic: $authentic);
    }

    /**
     * The header fields of the message a report returns, whole or as headers only.
     */
    private function original(Part $message): ?OriginalMessage
    {
        foreach ($message->walk() as $part) {
            if (null !== $part->message) {
                return new OriginalMessage($part->message->headers);
            }

            if (\in_array($part->type, ['text/rfc822-headers', 'message/global-headers'], true)) {
                return new OriginalMessage(Headers::parse($part->body));
            }
        }

        return null;
    }

    private function tag(Headers $headers): ?string
    {
        if (!$this->verp instanceof Verp) {
            return null;
        }

        foreach ($this->envelopeFields as $field) {
            foreach ($headers->all($field) as $value) {
                foreach (explode(',', $value) as $address) {
                    $tag = $this->verp->decode($address);

                    if (null !== $tag) {
                        return $tag;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param list<Recipient> $recipients
     */
    private function worst(array $recipients): BounceKind
    {
        foreach ([BounceKind::Hard, BounceKind::Soft, BounceKind::Delivered] as $kind) {
            foreach ($recipients as $recipient) {
                if ($recipient->kind === $kind) {
                    return $kind;
                }
            }
        }

        return BounceKind::Unknown;
    }

    private function text(Rule $rule, BounceReport $report): string
    {
        $headers = $report->message->headers;

        return match ($rule->field) {
            Field::Subject => (string) $headers->decoded('Subject'),
            Field::From => (string) $headers->get('From'),
            Field::Body => Texts::body($report->message, $this->limits->maxText),
            Field::Diagnostic => implode("\n", array_filter(array_map(static fn (Recipient $recipient): ?string => $recipient->diagnostic, $report->recipients), static fn (?string $diagnostic): bool => null !== $diagnostic)),
            Field::Header => implode("\n", $headers->all((string) $rule->header)),
        };
    }
}
