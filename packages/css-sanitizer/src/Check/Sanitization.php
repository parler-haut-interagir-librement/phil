<?php

declare(strict_types=1);

namespace Phil\CssSanitizer\Check;

use Phil\CssSanitizer\AtRuleKind;
use Phil\CssSanitizer\Policy;
use Phil\CssSanitizer\Removal;
use Phil\CssSanitizer\RemovalReason;
use Phil\CssSanitizer\Sanitized;
use Phil\CssSanitizer\Serializer;
use Phil\CssSanitizer\Sheet\GroupRule;
use Phil\CssSanitizer\Sheet\StyleRule;
use Phil\CssSanitizer\Sheet\StyleSheet;
use Phil\CssSanitizer\Syntax\AtRule;
use Phil\CssSanitizer\Syntax\ComponentValue;
use Phil\CssSanitizer\Syntax\Declaration;
use Phil\CssSanitizer\Syntax\Malformed;
use Phil\CssSanitizer\Syntax\Parser;
use Phil\CssSanitizer\Syntax\QualifiedRule;
use Phil\CssSanitizer\Syntax\SimpleBlock;
use Phil\CssSanitizer\Syntax\Token;
use Phil\CssSanitizer\Syntax\TokenType;
use Phil\CssSanitizer\UrlKind;

/**
 * One pass of the sanitizer over a parsed source: the rules and declarations it keeps, and the removals it records.
 *
 * @internal
 */
final class Sanitization
{
    /** The descriptors of `@font-face` that load nothing but through `src`. */
    private const array FONT_DESCRIPTORS = [
        'font-family', 'src', 'font-style', 'font-weight', 'font-stretch', 'font-display', 'unicode-range',
        'font-feature-settings', 'font-variation-settings', 'ascent-override', 'descent-override',
        'line-gap-override', 'size-adjust',
    ];

    /** The descriptors `@page` adds to the policy's properties. */
    private const array PAGE_DESCRIPTORS = ['size', 'marks', 'bleed', 'page-orientation'];

    /** The sixteen margin boxes of CSS Paged Media. */
    private const array MARGIN_BOXES = [
        'top-left-corner', 'top-left', 'top-center', 'top-right', 'top-right-corner', 'bottom-left-corner',
        'bottom-left', 'bottom-center', 'bottom-right', 'bottom-right-corner', 'left-top', 'left-middle',
        'left-bottom', 'right-top', 'right-middle', 'right-bottom',
    ];

    /** @var list<Removal> */
    private array $removals = [];

    private int $omitted = 0;

    private readonly Selectors $selectors;

    private readonly Preludes $preludes;

    private readonly Values $values;

    private readonly Imports $imports;

    private readonly FontSources $fontSources;

    /** @var list<ComponentValue> */
    private readonly array $scope;

    public function __construct(
        private readonly Policy $policy,
        private readonly Parser $parser,
        private readonly Serializer $serializer,
    ) {
        $this->selectors = new Selectors($policy);
        $this->preludes = new Preludes($policy);
        $this->values = new Values($policy, $serializer);
        $this->imports = new Imports($policy, $this->preludes, $this->values, $serializer);
        $this->fontSources = new FontSources($this->values, $serializer);
        $this->scope = null === $policy->scope ? [] : [...$parser->parseComponentValues($policy->scope), new Token(TokenType::Whitespace, ' ')];
    }

    private function remove(RemovalReason $reason, string $subject, int $line): void
    {
        if ($this->listsMore()) {
            $this->removals[] = new Removal($reason, $subject, $line);
        }
    }

    /**
     * Lists a removal while fewer than `Sanitized::MAX_REMOVALS` are, and counts it otherwise.
     */
    private function record(Removal $removal): void
    {
        if ($this->listsMore()) {
            $this->removals[] = $removal;
        }
    }

    private function listsMore(): bool
    {
        if (\count($this->removals) < Sanitized::MAX_REMOVALS) {
            return true;
        }

        ++$this->omitted;

        return false;
    }

    /**
     * @return list<Removal> the first `Sanitized::MAX_REMOVALS` removals
     */
    public function removals(): array
    {
        return $this->removals;
    }

    /** The removals past the first `Sanitized::MAX_REMOVALS`, counted but not listed. */
    public function omittedRemovals(): int
    {
        return $this->omitted;
    }

    /**
     * The rules of a style sheet: its leading `@import` rules apart, then the others.
     *
     * @param list<QualifiedRule|AtRule|Malformed> $rules
     */
    public function sheet(array $rules): StyleSheet
    {
        $imports = [];
        $rest = [];

        foreach ($rules as $rule) {
            if ([] === $rest && $rule instanceof AtRule && 'import' === $rule->lowerName()) {
                $import = $this->imports->check($rule);

                if ($import instanceof Removal) {
                    $this->record($import);
                } else {
                    $imports[] = $import;
                }
            } else {
                $rest[] = $rule;
            }
        }

        return new StyleSheet($this->rules($rest), $imports);
    }

    /**
     * @param list<QualifiedRule|AtRule|Malformed> $rules
     *
     * @return list<StyleRule|GroupRule>
     */
    private function rules(array $rules): array
    {
        $kept = [];

        foreach ($rules as $rule) {
            $sanitized = match (true) {
                $rule instanceof QualifiedRule => $this->styleRule($rule),
                $rule instanceof AtRule => $this->atRule($rule),
                default => $this->malformed($rule),
            };

            if (null !== $sanitized) {
                $kept[] = $sanitized;
            }
        }

        return $kept;
    }

    /**
     * The declarations of a style rule or a `style` attribute; at-rules among them are removed.
     *
     * @param list<Declaration|AtRule|Malformed> $items
     *
     * @return list<Declaration>
     */
    public function styleDeclarations(array $items): array
    {
        $declarations = [];

        foreach ($items as $item) {
            if ($item instanceof Declaration) {
                $declarations[] = $this->declaration($item, $this->allowsStyleProperty(...));
            } elseif ($item instanceof AtRule) {
                $this->remove(RemovalReason::AtRule, '@'.$item->name, $item->line);
            } else {
                $this->malformed($item);
            }
        }

        return array_values(array_filter($declarations));
    }

    private function malformed(Malformed $malformed): null
    {
        $this->remove(RemovalReason::Syntax, trim($this->serializer->componentValues($malformed->content)), $malformed->line);

        return null;
    }

    /**
     * A style rule, at the top level of the sheet or of a group rule (level 0), or nested in another style rule.
     */
    private function styleRule(QualifiedRule $rule, int $level = 0): ?StyleRule
    {
        $selectors = [];

        foreach (Parser::splitAtCommas($rule->prelude) as $selector) {
            if (0 === $level ? $this->selectors->allows($selector) : $this->selectors->allowsNested($selector)) {
                $selectors[] = 0 === $level ? [...$this->scope, ...$selector] : $selector;
            } else {
                $this->remove(RemovalReason::Selector, $this->serializer->componentValues($selector), $rule->line);
            }
        }

        [$declarations, $rules] = $this->styleBlock($rule->block->value, $level);

        return [] === $selectors || ([] === $declarations && [] === $rules) ? null : new StyleRule($selectors, $declarations, $rules);
    }

    /**
     * The contents of a style rule, or of a group rule nested in one, at a nesting level: its declarations, and the
     * rules nested in it while the policy allows one more level.
     *
     * @param list<ComponentValue> $values
     *
     * @return array{list<Declaration>, list<StyleRule|GroupRule>}
     */
    private function styleBlock(array $values, int $level): array
    {
        $declarations = [];
        $rules = [];

        foreach ($this->parser->blockContents($values) as $item) {
            $kept = match (true) {
                $item instanceof Declaration => $this->declaration($item, $this->allowsStyleProperty(...)),
                $item instanceof Malformed => $this->malformed($item),
                $level >= $this->policy->maxNesting => $this->tooNested($item),
                $item instanceof QualifiedRule => $this->styleRule($item, $level + 1),
                default => $this->nestedGroup($item, $level + 1),
            };

            if ($kept instanceof Declaration) {
                $declarations[] = $kept;
            } elseif (null !== $kept) {
                $rules[] = $kept;
            }
        }

        return [$declarations, $rules];
    }

    private function tooNested(QualifiedRule|AtRule $rule): null
    {
        $subject = $rule instanceof AtRule ? '@'.$rule->name : trim($this->serializer->componentValues($rule->prelude));
        $this->remove(RemovalReason::Nesting, $subject, $rule->line);

        return null;
    }

    /**
     * An `@media` or `@supports` rule nested in a style rule: its declarations apply to the elements of that rule, and
     * it may hold nested style rules of its own.
     */
    private function nestedGroup(AtRule $rule, int $level): ?GroupRule
    {
        $name = $rule->lowerName();
        $kind = AtRuleKind::tryFrom($name);

        if (!\in_array($kind, [AtRuleKind::Media, AtRuleKind::Supports], true) || !$this->policy->allowsAtRule($kind)) {
            $this->remove(RemovalReason::AtRule, '@'.$rule->name, $rule->line);

            return null;
        }

        $prelude = Parser::trim($rule->prelude);

        if (!(AtRuleKind::Media === $kind ? $this->preludes->media($prelude) : $this->preludes->supports($prelude))) {
            $this->remove(RemovalReason::Prelude, trim('@'.$rule->name.' '.$this->serializer->componentValues($prelude)), $rule->line);

            return null;
        }

        if (!$rule->block instanceof SimpleBlock) {
            $this->remove(RemovalReason::Syntax, '@'.$rule->name, $rule->line);

            return null;
        }

        [$declarations, $rules] = $this->styleBlock($rule->block->value, $level);

        return [] === $declarations && [] === $rules ? null : new GroupRule($name, $prelude, $declarations, $rules);
    }

    private function atRule(AtRule $rule): ?GroupRule
    {
        $name = $rule->lowerName();
        $kind = AtRuleKind::tryFrom($name);

        if (AtRuleKind::Import === $kind) {
            $this->remove(RemovalReason::Import, '@'.$rule->name, $rule->line);

            return null;
        }

        if (null === $kind || !$this->policy->allowsAtRule($kind)) {
            $this->remove(RemovalReason::AtRule, '@'.$rule->name, $rule->line);

            return null;
        }

        $prelude = Parser::trim($rule->prelude);
        $allowed = match ($kind) {
            AtRuleKind::Media => $this->preludes->media($prelude),
            AtRuleKind::Supports => $this->preludes->supports($prelude),
            AtRuleKind::Page => $this->preludes->page($prelude),
            AtRuleKind::FontFace => [] === $prelude,
        };

        if (!$allowed) {
            $this->remove(RemovalReason::Prelude, trim('@'.$rule->name.' '.$this->serializer->componentValues($prelude)), $rule->line);

            return null;
        }

        if (!$rule->block instanceof SimpleBlock) {
            $this->remove(RemovalReason::Syntax, '@'.$rule->name, $rule->line);

            return null;
        }

        $group = match ($kind) {
            AtRuleKind::Media, AtRuleKind::Supports => new GroupRule($name, $prelude, [], $this->rules($this->parser->rules($rule->block->value))),
            AtRuleKind::Page => $this->page($prelude, $this->parser->declarations($rule->block->value)),
            AtRuleKind::FontFace => $this->fontFace($this->parser->declarations($rule->block->value), $rule->line),
        };

        return !$group instanceof GroupRule || ([] === $group->declarations && [] === $group->rules) ? null : $group;
    }

    /**
     * @param list<ComponentValue>               $prelude
     * @param list<Declaration|AtRule|Malformed> $items
     */
    private function page(array $prelude, array $items): GroupRule
    {
        $declarations = [];
        $margins = [];

        foreach ($items as $item) {
            if ($item instanceof Declaration) {
                $declarations[] = $this->declaration($item, $this->allowsPageProperty(...));
            } elseif ($item instanceof Malformed) {
                $this->malformed($item);
            } elseif (\in_array($item->lowerName(), self::MARGIN_BOXES, true) && [] === Parser::trim($item->prelude) && null !== $item->block) {
                $margin = [];

                foreach ($this->parser->declarations($item->block->value) as $inner) {
                    if ($inner instanceof Declaration) {
                        $margin[] = $this->declaration($inner, $this->allowsPageProperty(...));
                    } elseif ($inner instanceof AtRule) {
                        $this->remove(RemovalReason::AtRule, '@'.$inner->name, $inner->line);
                    } else {
                        $this->malformed($inner);
                    }
                }

                $margin = array_values(array_filter($margin));

                if ([] !== $margin) {
                    $margins[] = new GroupRule($item->lowerName(), [], $margin);
                }
            } else {
                $this->remove(RemovalReason::AtRule, '@'.$item->name, $item->line);
            }
        }

        return new GroupRule('page', $prelude, array_values(array_filter($declarations)), $margins);
    }

    /**
     * @param list<Declaration|AtRule|Malformed> $items
     */
    private function fontFace(array $items, int $line): ?GroupRule
    {
        $descriptors = [];

        foreach ($items as $item) {
            if (!$item instanceof Declaration) {
                $this->styleDeclarations([$item]);

                continue;
            }

            $descriptor = 'src' === $item->property()
                ? $this->fontSources($item)
                : $this->declaration($item, static fn (string $name): bool => \in_array($name, self::FONT_DESCRIPTORS, true));

            if ($descriptor instanceof Declaration) {
                $descriptors[$descriptor->property()] = $descriptor;
            }
        }

        if (!isset($descriptors['font-family'], $descriptors['src'])) {
            $this->remove(RemovalReason::FontSource, '@font-face', $line);

            return null;
        }

        return new GroupRule('font-face', [], array_values($descriptors));
    }

    /** The `src` descriptor of `@font-face`, its refused sources removed one by one. */
    private function fontSources(Declaration $src): ?Declaration
    {
        [$sources, $removals] = $this->fontSources->check($src);
        foreach ($removals as $removal) {
            $this->record($removal);
        }

        return $sources;
    }

    /**
     * A declaration the property check allows, its value checked; null when it is removed.
     *
     * @param callable(string): bool $allowsProperty
     */
    private function declaration(Declaration $declaration, callable $allowsProperty): ?Declaration
    {
        $property = $declaration->property();

        if (\in_array($property, Policy::DENIED_PROPERTIES, true)) {
            $this->remove(RemovalReason::DeniedProperty, $property, $declaration->line);

            return null;
        }

        if ($declaration->isCustomProperty() ? !$this->policy->customProperties : !$allowsProperty($property)) {
            $this->remove(RemovalReason::Property, $property, $declaration->line);

            return null;
        }

        if ([] === $declaration->value) {
            $this->remove(RemovalReason::Value, $property, $declaration->line);

            return null;
        }

        $value = $this->values->value($declaration->value, UrlKind::Image);

        if ($value instanceof Removal) {
            $this->record($value);

            return null;
        }

        $important = $declaration->important;

        if ($important && !$this->policy->important) {
            $this->remove(RemovalReason::Important, $property, $declaration->line);
            $important = false;
        }

        return new Declaration($declaration->name, $value, $important, $declaration->line);
    }

    private function allowsStyleProperty(string $property): bool
    {
        return isset($this->policy->properties[$property]);
    }

    private function allowsPageProperty(string $property): bool
    {
        return isset($this->policy->properties[$property]) || \in_array($property, self::PAGE_DESCRIPTORS, true);
    }
}
