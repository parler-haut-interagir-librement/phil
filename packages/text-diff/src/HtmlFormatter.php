<?php

declare(strict_types=1);

namespace Phil\TextDiff;

/**
 * A difference as HTML: kept text as it is, deleted text in `<del>`, inserted text in `<ins>`.
 *
 * A difference of plain text — words, lines, paragraphs — is escaped, line breaks kept: show it in an element styled
 * `white-space: pre-wrap` to see them. A difference of HTML keeps the structure of the new text: its tags and
 * character references are written as they are, inserted ones included, while deleted tags are left out, so that
 * `<ins>` and `<del>` only ever wrap text and the result is as well formed as the new HTML; the text between tags,
 * kept, deleted or inserted, is escaped, so that no text of either side becomes markup. A change of a tag alone —
 * bold made italic — shows the new tag without a mark; the change list has it. Elements whose content is not HTML,
 * `<script>` and `<style>`, are not compared as such: their content is text like any other.
 */
final readonly class HtmlFormatter
{
    public function format(Diff $diff): string
    {
        $markup = $diff->tokenizer->isMarkup();
        $html = '';

        foreach ($diff->operations as $operation) {
            $html .= match ($operation->kind) {
                OperationKind::Equal => $markup ? implode('', array_map($this->markup(...), $operation->tokens)) : htmlspecialchars($operation->text(), \ENT_QUOTES),
                OperationKind::Delete => $this->marked('del', $operation, $markup),
                OperationKind::Insert => $this->marked('ins', $operation, $markup),
            };
        }

        return $html;
    }

    private function marked(string $tag, Operation $operation, bool $markup): string
    {
        if (!$markup) {
            return '<'.$tag.'>'.htmlspecialchars($operation->text(), \ENT_QUOTES).'</'.$tag.'>';
        }

        $html = '';
        $text = '';

        foreach ($operation->tokens as $token) {
            if ($this->isTag($token)) {
                $html .= $this->text($tag, $text).('ins' === $tag ? $token : '');
                $text = '';
            } else {
                $text .= $this->markup($token);
            }
        }

        return $html.$this->text($tag, $text);
    }

    /**
     * A token of HTML as it is written: a tag as it is, any other text escaped, its character references kept.
     */
    private function markup(string $token): string
    {
        return $this->isTag($token) ? $token : htmlspecialchars($token, \ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Whether a token of HTML is a tag or a comment: the tokenizer gives one only whole, any other `<` alone.
     */
    private function isTag(string $token): bool
    {
        return str_starts_with($token, '<') && str_ends_with($token, '>');
    }

    /**
     * Text between two tags, marked; white space alone is kept unmarked when inserted and left out when deleted, so
     * that no mark lands where only elements may stand, between two list items or table cells.
     */
    private function text(string $tag, string $text): string
    {
        if ('' === trim($text)) {
            return 'ins' === $tag ? $text : '';
        }

        return '<'.$tag.'>'.$text.'</'.$tag.'>';
    }
}
