<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Check;

use Phil\CssSanitizer\AtRuleKind;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Removal;
use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Sheet\ImportRule;
use Phil\CssSanitizer\Syntax\AtRule;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;
use Phil\CssSanitizer\UrlKind;

/**
 * Checks an `@import` rule at the start of a style sheet: allowed by the policy, without a block, its URL a style sheet
 * the rewriter allows — never a fragment, which would load the page itself —, its conditions a `supports()` condition
 * and media queries.
 *
 * @internal
 */
final readonly class Imports
{
    public function __construct(
        private Policy $policy,
        private Preludes $preludes,
        private Values $values,
        private Serializer $serializer,
    ) {
    }

    public function check(AtRule $rule): ImportRule|Removal
    {
        if (!$this->policy->allowsAtRule(AtRuleKind::Import) || $rule->block instanceof SimpleBlock) {
            return new Removal(RemovalReason::Import, '@'.$rule->name, $rule->line);
        }

        $prelude = Parser::trim($rule->prelude);
        $first = $prelude[0] ?? null;
        $conditions = Parser::trim(\array_slice($prelude, 1));
        $url = match (true) {
            $first instanceof Token && (TokenType::String === $first->type || TokenType::Url === $first->type) => $first->value,
            null === $first => null,
            default => Values::urlOf($first),
        };

        if (null === $url || !$this->preludes->import($conditions)) {
            return new Removal(RemovalReason::Prelude, trim('@'.$rule->name.' '.$this->serializer->componentValues($prelude)), $rule->line);
        }

        $token = str_starts_with($url, '#') ? new Removal(RemovalReason::Url, $url, $rule->line) : $this->values->url($url, UrlKind::StyleSheet, $rule->line);

        return $token instanceof Removal ? $token : new ImportRule($token->value, $conditions);
    }
}
