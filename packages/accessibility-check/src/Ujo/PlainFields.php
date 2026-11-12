<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Ujo;

/**
 * The fields of block data that never hold HTML, by block type: source code, formulas, chart configurations, the plain
 * texts of images, frames and players. Every other text of a block that holds a `<` is read as HTML. A field is named
 * by its key, wherever it is in the data; `*` names every field of the type.
 */
final readonly class PlainFields
{
    /**
     * @var array<string, list<string>>
     */
    public const array DEFAULTS = [
        'code' => ['code', 'language'],
        'mermaid' => ['code'],
        'math' => ['latex', 'mathml'],
        'chart' => ['*'],
        'image' => ['alt'],
        'embed' => ['title'],
        'video' => ['title'],
        'audio' => ['title'],
        'personality' => ['name', 'description'],
        'button' => ['text'],
        'attaches' => ['title'],
        'toc' => ['title'],
        'index' => ['title', 'term'],
        'abbreviations' => ['title'],
        'credit' => ['title'],
        'watermark' => ['text'],
        'banner' => ['text'],
        'qrCode' => ['data'],
        'statblock' => ['name'],
    ];

    /**
     * @param array<string, list<string>> $fields the plain fields by block type, replacing the defaults of each type
     *                                            given
     */
    public function __construct(
        public array $fields = [],
    ) {
    }

    /**
     * Whether the field of this key, in a block of this type, is plain text.
     */
    public function isPlain(string $type, string $key): bool
    {
        $plain = $this->fields[$type] ?? self::DEFAULTS[$type] ?? [];

        return \in_array('*', $plain, true) || \in_array($key, $plain, true);
    }
}
