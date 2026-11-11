<?php

declare(strict_types=1);

namespace Phil\StructuredData\Type;

use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;

/**
 * A `FAQPage`: its questions, each a `Question` with its `Answer` as the page shows them.
 */
final class FAQPage
{
    /**
     * @param list<array{question: string|LanguageString, answer: string|LanguageString}> $questions  in the order of the page
     * @param array<string, mixed>                                                        $properties the other properties, which the named ones replace
     */
    public static function of(array $questions, ?string $url = null, ?string $id = null, array $properties = []): Node
    {
        $entities = [];

        foreach ($questions as $question) {
            $entities[] = Node::of('Question', ['name' => $question['question'], 'acceptedAnswer' => Node::of('Answer', ['text' => $question['answer']])]);
        }

        return Node::of('FAQPage', Properties::merge($properties, ['url' => $url, 'mainEntity' => $entities]), $id);
    }
}
