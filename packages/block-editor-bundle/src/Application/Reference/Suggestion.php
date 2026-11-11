<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Application\Reference;

use Phil\BlockEditorBundle\Application\Exception\InvalidSuggestion;

/**
 * Something a writer may mention with `@` or link to with `[[`: a reference `kind:identifier` written in the
 * document, the label shown and inserted, and optionally its address and a hint shown beside the label.
 */
final readonly class Suggestion
{
    public const string REFERENCE = '~^[a-z][a-z0-9_-]{0,31}:[A-Za-z0-9_./-]{1,128}$~D';

    /**
     * @throws InvalidSuggestion
     */
    public function __construct(
        public string $reference,
        public string $label,
        public ?string $url = null,
        public ?string $hint = null,
    ) {
        if (1 !== preg_match(self::REFERENCE, $reference)) {
            throw InvalidSuggestion::reference($reference);
        }

        $length = mb_strlen($label);

        if (0 === $length || $length > 200) {
            throw InvalidSuggestion::label();
        }
    }

    /**
     * @return array{reference: string, label: string, url: ?string, hint: ?string}
     */
    public function toArray(): array
    {
        return ['reference' => $this->reference, 'label' => $this->label, 'url' => $this->url, 'hint' => $this->hint];
    }
}
