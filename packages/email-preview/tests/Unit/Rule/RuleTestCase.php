<?php

declare(strict_types=1);

namespace Phil\EmailPreview\Tests\Unit\Rule;

use Phil\EmailPreview\CheckedEmail;
use Phil\EmailPreview\Contract\Rule;
use Phil\EmailPreview\Sending;
use PHPUnit\Framework\TestCase;

/**
 * Runs one rule on an email and writes each finding on one line.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * @return list<string>
     */
    protected static function findings(Rule $rule, string $html = '<p>Hello</p>', string $text = 'Hello', string $subject = 'Hello', Sending $sending = new Sending()): array
    {
        $lines = [];

        foreach ($rule->check(new CheckedEmail($subject, $html, $text, $sending)) as $finding) {
            self::assertSame($rule->name(), $finding->rule);
            $lines[] = $finding->toText();
        }

        return $lines;
    }
}
