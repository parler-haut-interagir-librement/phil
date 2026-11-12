<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Segmentation;

/**
 * How finely a document is cut: one segment per text field of a block, or one per sentence of each field.
 */
enum Granularity: string
{
    case Block = 'block';
    case Sentence = 'sentence';
}
