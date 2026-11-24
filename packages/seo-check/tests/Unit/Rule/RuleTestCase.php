<?php

declare(strict_types=1);

namespace Phil\SeoCheck\Tests\Unit\Rule;

use Phil\SeoCheck\CheckedPage;
use Phil\SeoCheck\Contract\SeoRule;
use PHPUnit\Framework\TestCase;

/**
 * Runs one rule and writes each finding on one line: its severity, its message key, its parameters and its subject.
 * Every finding must carry the rule's name.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * @return list<string>
     */
    protected static function lines(SeoRule $rule, CheckedPage $page): array
    {
        $lines = [];

        foreach ($rule->check($page) as $finding) {
            self::assertSame($rule->name(), $finding->rule);
            $parameters = [];

            foreach ($finding->message->parameters as $name => $value) {
                $parameters[] = $name.'='.$value;
            }

            $lines[] = $finding->severity->value.' '.$finding->message->key
                .([] === $parameters ? '' : ' ('.implode(', ', $parameters).')')
                .(null === $finding->subject ? '' : ' @ '.$finding->subject);
        }

        return $lines;
    }
}
