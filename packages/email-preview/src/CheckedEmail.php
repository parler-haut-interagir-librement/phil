<?php

declare(strict_types=1);

namespace Phil\EmailPreview;

use Dom\HTMLDocument;
use Phil\EmailPreview\Exception\EmailTooLarge;
use Phil\EmailTemplatesBundle\Application\Rendering\RenderedEmail;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Header\HeaderInterface;

/**
 * An email as the rules read it: its subject, its HTML parsed once by the HTML5 parser of PHP, its plain-text part and
 * how it is sent.
 */
final readonly class CheckedEmail
{
    /** The largest HTML the check parses, in bytes: twenty times what Gmail shows before it clips a message. */
    public const int MAX_HTML_BYTES = 2 * 1024 * 1024;

    public HTMLDocument $document;

    /**
     * @throws EmailTooLarge when the HTML is larger than MAX_HTML_BYTES
     */
    public function __construct(
        public string $subject,
        public string $html,
        public string $text,
        public Sending $sending = new Sending(),
    ) {
        if (\strlen($html) > self::MAX_HTML_BYTES) {
            throw EmailTooLarge::html(\strlen($html), self::MAX_HTML_BYTES);
        }

        $this->document = HTMLDocument::createFromString($html, \LIBXML_NOERROR, 'UTF-8');
    }

    /**
     * A preview rendered by phil/email-templates-bundle, as it would be sent.
     */
    public static function fromRendered(RenderedEmail $email, Sending $sending = new Sending()): self
    {
        return new self($email->subject, $email->html, $email->text, $sending);
    }

    /**
     * An email built for Symfony Mailer: its headers are those of the sending, its recipients identify it.
     */
    public static function fromMime(Email $email, bool $bulk = false): self
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof HeaderInterface) {
                $headers[$header->getName()] ??= $header->getBodyAsString();
            }
        }

        $tokens = [];

        foreach ([...$email->getTo(), ...$email->getCc(), ...$email->getBcc()] as $address) {
            $tokens[] = $address->getAddress();
        }

        return new self(
            $email->getSubject() ?? '',
            self::body($email->getHtmlBody()),
            self::body($email->getTextBody()),
            new Sending($bulk, $headers, $tokens),
        );
    }

    /**
     * @param resource|string|null $body
     */
    private static function body(mixed $body): string
    {
        if (!\is_resource($body)) {
            return (string) $body;
        }

        return (string) stream_get_contents($body, self::MAX_HTML_BYTES + 1, 0);
    }
}
