<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Application;

use Phil\FaqBundle\Application\View\FaqPage;
use Phil\FaqBundle\Application\View\QuestionView;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\StructuredData\Graph;
use Phil\StructuredData\Type\FAQPage as FaqPageType;

/**
 * The questions a page shows, as a schema.org `FAQPage`: each `Question` with its `acceptedAnswer`, the answer's
 * purified HTML as its text, in the language of the page. It describes the page for search engines and assistants
 * that read JSON-LD; how a search engine shows it is that engine's choice.
 *
 * A page that shows no question gives an empty graph, written as nothing.
 */
final readonly class FaqStructuredData
{
    /**
     * @param string|null $url  the address of the page
     * @param string|null $name the title of the page
     */
    public function graph(FaqPage $page, ?string $url = null, ?string $name = null): Graph
    {
        $questions = $page->questions();

        if ([] === $questions) {
            return Graph::of();
        }

        $entities = array_map(static fn (QuestionView $question): array => ['question' => $question->question, 'answer' => $question->answerHtml], $questions);
        $tag = Locale::fromString($page->locale)->tag();

        return Graph::of(FaqPageType::of($entities, $url, properties: ['inLanguage' => $tag, 'name' => $name]));
    }
}
