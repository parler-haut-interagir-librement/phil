<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization\Anonymizer;

use Override;
use Phil\PrivacyBundle\Domain\Anonymization\AnonymizationContext;
use Phil\PrivacyBundle\Domain\Contract\Anonymizer;

/**
 * `lorem` — placeholder Latin for free text such as a note or a message, one sentence of `words` words (default 12).
 */
final readonly class LoremAnonymizer implements Anonymizer
{
    private const array WORDS = [
        'lorem', 'ipsum', 'dolor', 'sit', 'amet', 'consectetur', 'adipiscing', 'elit', 'sed', 'do', 'eiusmod',
        'tempor', 'incididunt', 'ut', 'labore', 'et', 'dolore', 'magna', 'aliqua', 'enim', 'ad', 'minim', 'veniam',
        'quis', 'nostrud', 'exercitation', 'ullamco', 'laboris', 'nisi', 'aliquip', 'ex', 'ea', 'commodo',
        'consequat', 'duis', 'aute', 'irure', 'in', 'reprehenderit', 'voluptate', 'velit', 'esse', 'cillum',
        'fugiat', 'nulla', 'pariatur', 'excepteur', 'sint', 'occaecat', 'cupidatat', 'non', 'proident', 'sunt',
        'culpa', 'qui', 'officia', 'deserunt', 'mollit', 'anim', 'id', 'est', 'laborum',
    ];

    #[Override]
    public function anonymize(string|int|float|bool $value, AnonymizationContext $context): string
    {
        $words = [];

        for ($count = $context->options->int('words', 12); $count > 0; --$count) {
            $words[] = $context->pick(self::WORDS);
        }

        return ucfirst(implode(' ', $words)).'.';
    }
}
