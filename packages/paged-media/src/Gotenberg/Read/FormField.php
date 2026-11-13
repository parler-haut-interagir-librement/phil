<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;

/**
 * A form field as `/forms/pdf/form` lists it, in the shape `set-form` fills: its fully qualified name, its kind, its
 * value, the pages it sits on, the states a checkbox or a radio group takes besides `Off`, the options of a choice.
 */
final readonly class FormField
{
    /**
     * @param string|list<string> $value   a list for a list that selects several
     * @param list<int>           $pages
     * @param list<string>        $states
     * @param list<string>        $options
     */
    public function __construct(
        public string $name,
        public FormFieldType $type,
        public string|array $value,
        public array $pages,
        public array $states = [],
        public array $options = [],
        public bool $readOnly = false,
        public bool $required = false,
        public bool $multiSelect = false,
        public bool $editable = false,
        public ?int $maxLength = null,
    ) {
    }

    /**
     * @internal
     */
    public static function fromJson(JsonValue $json): self
    {
        $type = $json->string('type');

        return new self(
            $json->string('name'),
            FormFieldType::tryFrom($type) ?? throw UnexpectedResponse::because(\sprintf('Gotenberg listed a form field of an unknown type "%s".', $type)),
            $json->stringOrStrings('value'),
            $json->ints('pages'),
            $json->strings('states'),
            $json->strings('options'),
            $json->flag('readOnly'),
            $json->flag('required'),
            $json->flag('multiSelect'),
            $json->flag('editable'),
            $json->optionalInt('maxLength'),
        );
    }
}
