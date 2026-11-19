<?php

declare(strict_types=1);

namespace Phil\VirtualClassroom\BigBlueButton;

use DOMDocument;
use DOMDocumentType;
use DOMElement;
use DOMNode;
use DOMXPath;
use Phil\VirtualClassroom\Exception\InvalidResponse;
use Phil\VirtualClassroom\Exception\ProviderRefused;

/**
 * An answer of the BigBlueButton API: an XML `response` whose `returncode` is `SUCCESS` or `FAILED`, with a stable
 * `messageKey`. A document type is refused, so that no entity is ever declared, let alone expanded; nothing is read
 * from the network.
 *
 * @internal
 */
final readonly class XmlAnswer
{
    private function __construct(
        private DOMXPath $xpath,
        public ?string $messageKey,
    ) {
    }

    /**
     * @param list<string> $tolerated the message keys of a `FAILED` answer that are an answer, not an error
     *
     * @throws InvalidResponse when the answer is not the XML of a `response`
     * @throws ProviderRefused when the server failed the call with another message key
     */
    public static function read(string $xml, string $label, array $tolerated = []): self
    {
        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = '' !== trim($xml) && $document->loadXML($xml, \LIBXML_NONET | \LIBXML_COMPACT);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded || $document->doctype instanceof DOMDocumentType || 'response' !== $document->documentElement?->nodeName) {
            throw InvalidResponse::because(\sprintf('The answer to "%s" is not the XML of a response.', $label));
        }

        $answer = new self(new DOMXPath($document), null);
        $code = $answer->text('/response/returncode');
        $key = $answer->text('/response/messageKey');

        if ('SUCCESS' === $code) {
            return new self($answer->xpath, $key);
        }

        if ('FAILED' !== $code) {
            throw InvalidResponse::because(\sprintf('The answer to "%s" has no return code.', $label));
        }

        if (null !== $key && \in_array($key, $tolerated, true)) {
            return new self($answer->xpath, $key);
        }

        throw ProviderRefused::because(\sprintf('The call "%s" was refused: %s.', $label, null === $key ? 'no message key' : self::printable($key)));
    }

    public function succeeded(): bool
    {
        return 'SUCCESS' === $this->text('/response/returncode');
    }

    /**
     * The text of the first element at the path, trimmed, or null when there is none.
     */
    public function text(string $path, ?DOMElement $context = null): ?string
    {
        $nodes = $this->xpath->query($path, $context);
        $node = false === $nodes ? null : $nodes->item(0);

        return $node instanceof DOMNode ? trim($node->textContent) : null;
    }

    /**
     * @return list<DOMElement>
     */
    public function elements(string $path, ?DOMElement $context = null): array
    {
        $nodes = $this->xpath->query($path, $context);
        $elements = [];

        foreach (false === $nodes ? [] : $nodes as $node) {
            if ($node instanceof DOMElement) {
                $elements[] = $node;
            }
        }

        return $elements;
    }

    /**
     * A key the server gave, kept to the characters a key holds, for a message.
     */
    private static function printable(string $key): string
    {
        return substr((string) preg_replace('/[^A-Za-z0-9_.-]/', '', $key), 0, 64);
    }
}
