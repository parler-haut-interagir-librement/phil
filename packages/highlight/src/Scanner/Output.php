<?php

declare(strict_types=1);

namespace Phil\Highlight\Scanner;

use Phil\Highlight\Token;
use Phil\Highlight\TokenType;

/**
 * The tokens of one tokenization, a text joined to the last token when they have the same type, an empty one dropped.
 *
 * @internal
 */
final class Output
{
    /** @var list<Token> */
    private array $tokens = [];

    private string $text = '';

    private ?TokenType $type = null;

    private ?Token $significant = null;

    public function add(string $text, ?TokenType $type = null): void
    {
        if ('' === $text) {
            return;
        }

        if ('' !== trim($text)) {
            $this->significant = new Token($text, $type);
        }

        if ($type !== $this->type) {
            $this->flush();
            $this->type = $type;
        }

        $this->text .= $text;
    }

    /**
     * @param list<Token> $tokens
     */
    public function append(array $tokens): void
    {
        foreach ($tokens as $token) {
            $this->add($token->text, $token->type);
        }
    }

    /**
     * The last text added that is not only spaces, as it was added, before it was joined; `null` before the first.
     */
    public function significant(): ?Token
    {
        return $this->significant;
    }

    /**
     * @return list<Token>
     */
    public function tokens(): array
    {
        $this->flush();

        return $this->tokens;
    }

    private function flush(): void
    {
        if ('' !== $this->text) {
            $this->tokens[] = new Token($this->text, $this->type);
            $this->text = '';
        }
    }
}
