<?php

declare(strict_types=1);

namespace Phil\Highlight\Language;

use Override;
use Phil\Highlight\Language;
use Phil\Highlight\Scanner\Cursor;
use Phil\Highlight\Scanner\Output;

/**
 * PHP: a source with no opening tag is all code; a source with `<?php` or `<?=` is markup up to each opening tag, and
 * code from it to the next `?>`. The code is read by the code language given — `Definitions::php()` reads the tags as
 * meta —, the markup by the markup language given.
 */
final readonly class Php implements Language
{
    public function __construct(
        private Language $code,
        private Language $markup,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->code->name();
    }

    #[Override]
    public function aliases(): array
    {
        return $this->code->aliases();
    }

    #[Override]
    public function tokenize(string $code): array
    {
        if (!str_contains($code, '<?php') && !str_contains($code, '<?=')) {
            return $this->code->tokenize($code);
        }

        $cursor = new Cursor($code);
        $output = new Output();

        while (!$cursor->done()) {
            $open = $this->opening($code, $cursor->position) ?? \strlen($code);
            $markup = $cursor->take($open - $cursor->position);
            $output->append($this->markup->tokenize($markup));
            $output->append($this->code->tokenize($cursor->through('?>')));
        }

        return $output->tokens();
    }

    /**
     * Where the next opening tag is, from `$from`.
     */
    private function opening(string $code, int $from): ?int
    {
        $tags = array_filter([strpos($code, '<?php', $from), strpos($code, '<?=', $from)], is_int(...));

        return [] === $tags ? null : min($tags);
    }
}
