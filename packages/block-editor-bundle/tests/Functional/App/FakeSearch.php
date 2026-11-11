<?php

declare(strict_types=1);

namespace Phil\BlockEditorBundle\Tests\Functional\App;

use Override;
use Phil\BlockEditorBundle\Application\Reference\ReferenceSearch;
use Phil\BlockEditorBundle\Application\Reference\Suggestion;

/**
 * Two people, found by the start of their name.
 */
final class FakeSearch implements ReferenceSearch
{
    #[Override]
    public function search(string $query, int $limit): iterable
    {
        foreach (['ada' => 'Ada Lovelace', 'alan' => 'Alan Turing'] as $id => $name) {
            if (str_starts_with(strtolower($name), strtolower($query))) {
                yield new Suggestion('user:'.$id, $name, '/people/'.$id);
            }
        }
    }
}
