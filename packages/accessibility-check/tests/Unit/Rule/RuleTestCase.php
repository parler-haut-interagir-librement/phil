<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Rule;

use Phil\AccessibilityCheck\Contract\HtmlRule;
use Phil\AccessibilityCheck\Contract\UjoRule;
use Phil\AccessibilityCheck\Html\HtmlTarget;
use Phil\AccessibilityCheck\Policy;
use Phil\AccessibilityCheck\Ujo\UjoTarget;
use Phil\AccessibilityCheck\Violation;
use Phil\Ujo\Document;
use PHPUnit\Framework\TestCase;

/**
 * Runs one rule and writes each violation on one line: its message key, its parameters, where it is — the path of the
 * element, or the pointer of the UJO field and the path —, and its severity or criterion when it has its own.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * @return list<string>
     */
    protected static function html(HtmlRule $rule, string $html, Policy $policy = new Policy(), ?string $language = null): array
    {
        return self::lines($rule->checkHtml(HtmlTarget::parse($html, $policy, $language)));
    }

    /**
     * @param list<array<string, mixed>> $blocks
     *
     * @return list<string>
     */
    protected static function ujo(UjoRule $rule, array $blocks, Policy $policy = new Policy(), ?string $language = null): array
    {
        return self::lines($rule->checkUjo(UjoTarget::read(Document::fromArray(['blocks' => $blocks]), $policy, $language)));
    }

    /**
     * @param iterable<Violation> $violations
     *
     * @return list<string>
     */
    private static function lines(iterable $violations): array
    {
        $lines = [];

        foreach ($violations as $violation) {
            $parameters = [];

            foreach ($violation->message->parameters as $name => $value) {
                $parameters[] = $name.'='.$value;
            }

            $where = implode(' ', array_filter([$violation->location->pointer, $violation->location->element], static fn (?string $part): bool => null !== $part));
            $line = $violation->message->key.('' === $where ? '' : ' @ '.$where).([] === $parameters ? '' : ' ('.implode(', ', $parameters).')');
            $line .= null === $violation->severity ? '' : ' !'.$violation->severity->value;
            $line .= null === $violation->criterion ? '' : ' #'.$violation->criterion->value;
            $lines[] = $line;
        }

        return $lines;
    }
}
