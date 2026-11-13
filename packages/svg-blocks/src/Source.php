<?php

declare(strict_types=1);

namespace Phil\SvgBlocks;

use Phil\Ujo\Block;

/**
 * What a block's drawing is made from: the formula of `math`, the code of `mermaid`, the text of `qrCode`, and the
 * whole configuration of `chart` but its drawing; for any other type, its data but its drawing. A caption is no part
 * of it: it is written beside the drawing, not in it.
 */
final class Source
{
    public static function of(Block $block): string
    {
        $data = $block->data;

        return match ($block->type) {
            'math' => self::text($data['latex'] ?? null),
            'mermaid' => self::text($data['code'] ?? null),
            'qrCode' => self::text($data['data'] ?? null),
            default => self::json($data),
        };
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function json(array $data): string
    {
        unset($data['rendered']);

        return json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    private static function text(mixed $value): string
    {
        return \is_string($value) ? $value : '';
    }
}
