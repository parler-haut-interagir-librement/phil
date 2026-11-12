<?php

declare(strict_types=1);

namespace Phil\SearchBundle\Tests\Benchmark;

use Phil\SearchBundle\Application\SynonymExpander;
use Phil\SearchBundle\Domain\ValueObject\SearchQuery;
use Phil\SearchBundle\Domain\ValueObject\SynonymRule;
use Phil\SearchBundle\Tests\Unit\Fixtures\FixedSynonyms;

/**
 * Every search of a language with synonyms goes through the expander: the first one of a request reads the rules of
 * the language and builds their map, the next ones only look the phrases of the query up in it.
 *
 * @BeforeMethods("setUp")
 */
final class SynonymExpanderBench
{
    private SynonymExpander $expander;

    public function __construct()
    {
        $this->expander = new SynonymExpander([]);
    }

    /**
     * @param array{rules: int} $params
     */
    public function setUp(array $params): void
    {
        $rules = [];

        for ($i = 0; $i < $params['rules']; ++$i) {
            $rules[] = 0 === $i % 2 ? SynonymRule::equivalent(['mot'.$i, 'terme '.$i, 'autre mot '.$i]) : SynonymRule::oneWay(['forme'.$i], ['forme'.$i, 'préférée '.$i]);
        }

        $rules[] = SynonymRule::equivalent(['jdr', 'jeu de rôle']);
        $this->expander = new SynonymExpander([new FixedSynonyms(['fr' => $rules])]);
    }

    /**
     * The first search of a request: the map built, then a query of six words expanded.
     *
     * @ParamProviders({"sizes"})
     *
     * @Revs(5)
     *
     * @Iterations(3)
     */
    public function benchFirstSearch(): void
    {
        $this->expander->reset();
        $this->expander->expand(SearchQuery::of('un jeu de rôle et mot1 ici', language: 'fr'));
    }

    /**
     * A search once the map is built.
     *
     * @ParamProviders({"sizes"})
     *
     * @Revs(1000)
     *
     * @Iterations(3)
     */
    public function benchNextSearch(): void
    {
        $this->expander->expand(SearchQuery::of('un jeu de rôle et mot1 ici', language: 'fr'));
    }

    /**
     * @return iterable<string, array{rules: int}>
     */
    public function sizes(): iterable
    {
        yield '100 rules' => ['rules' => 100];
        yield '5k rules' => ['rules' => 5_000];
    }
}
