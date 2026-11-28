<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Application;

use Phil\EmailTemplatesBundle\Application\Rendering\EmailRenderer;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderOptions;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Turns a template into a Symfony Mailer `Email` ready to send: its subject, its HTML and its plain-text part rendered,
 * its images attached inline under their content identifier, so that `cid:logo` in the HTML shows them. The sender and
 * the recipients are the application's, on the returned email or on the one given; an email without a sender is given
 * the configured one, `phil_email_templates.from`.
 *
 * ```php
 * $mailer->send($composer->compose('ticket', ['ticket' => [...]], new RenderOptions(locale: 'fr'))->to($address));
 * ```
 */
final readonly class EmailComposer
{
    public function __construct(
        private EmailRenderer $renderer,
        private ?Address $from = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $variables
     */
    public function compose(string $key, array $variables = [], RenderOptions $options = new RenderOptions(), ?Email $email = null): Email
    {
        $composed = self::toEmail($this->renderer->render($key, $variables, $options), $email);

        if ($this->from instanceof Address && [] === $composed->getFrom()) {
            $composed->from($this->from);
        }

        return $composed;
    }

    /**
     * The rendered email written onto an `Email`, a new one by default.
     */
    public static function toEmail(RenderedEmail $rendered, ?Email $email = null): Email
    {
        $email ??= new Email();
        $email->subject($rendered->subject)->html($rendered->html)->text($rendered->text);

        foreach ($rendered->embeds as $embed) {
            $email->addPart(new DataPart($embed->body, $embed->name, $embed->contentType)->asInline());
        }

        return $email;
    }
}
