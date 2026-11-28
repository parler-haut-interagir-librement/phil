<?php

declare(strict_types=1);

namespace Phil\EmailTemplatesBundle\Infrastructure\Twig;

use Override;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\Binary\RangeBinary;
use Twig\Node\Expression\FilterExpression;
use Twig\Node\Expression\FunctionExpression;
use Twig\Node\Expression\TestExpression;
use Twig\Node\Expression\Variable\AssignContextVariable;
use Twig\Node\Expression\Variable\ContextVariable;
use Twig\Node\ForNode;
use Twig\Node\ModuleNode;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Bounds what a template may do and records what it uses, while it compiles: `for` loops nested at most two deep — a
 * list holds at most 1000 values, so a template walks at most a million —; the names of the variables the template
 * reads and those it sets, so that a variable the schema does not declare is reported when the template is saved; and
 * the tags, filters, functions and tests it calls, which the sandbox's policy checks then, before any render.
 *
 * What the last template compiled uses is read with `lastNames()` and `lastCalls()`; it is cleared when the next one
 * starts.
 */
final class LimitsVisitor implements NodeVisitorInterface
{
    public const int MAX_LOOP_DEPTH = 2;

    private int $depth = 0;

    /** @var array<string, true> */
    private array $read = [];

    /** @var array<string, true> */
    private array $set = [];

    /** @var array{tags: array<string, true>, filters: array<string, true>, functions: array<string, true>, tests: array<string, true>} */
    private array $calls = ['tags' => [], 'filters' => [], 'functions' => [], 'tests' => []];

    #[Override]
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ModuleNode) {
            $this->depth = 0;
            $this->read = [];
            $this->set = [];
            $this->calls = ['tags' => [], 'filters' => [], 'functions' => [], 'tests' => []];
        } else {
            $this->enter($node);
        }

        return $node;
    }

    #[Override]
    public function leaveNode(Node $node, Environment $env): Node
    {
        if ($node instanceof ForNode) {
            --$this->depth;
        }

        return $node;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    /**
     * The tags, filters, functions and tests the last template compiled calls, each once.
     *
     * @return array{tags: list<string>, filters: list<string>, functions: list<string>, tests: list<string>}
     */
    public function lastCalls(): array
    {
        return array_map(array_keys(...), $this->calls);
    }

    private function enter(Node $node): void
    {
        $this->recordCall($node);

        if ($node instanceof ForNode) {
            if (++$this->depth > self::MAX_LOOP_DEPTH) {
                throw new SyntaxError(\sprintf('Loops nest at most %d deep.', self::MAX_LOOP_DEPTH), $node->getTemplateLine(), $node->getSourceContext());
            }
        } elseif ($node instanceof AssignContextVariable) {
            $this->set[$this->name($node)] = true;
        } elseif ($node instanceof ContextVariable) {
            $this->read[$this->name($node)] = true;
        }
    }

    private function recordCall(Node $node): void
    {
        $tag = $node->getNodeTag();

        if (null !== $tag) {
            $this->calls['tags'][$tag] = true;
        }

        if ($node instanceof FilterExpression) {
            $this->calls['filters'][$this->name($node)] = true;
        } elseif ($node instanceof FunctionExpression) {
            $this->calls['functions'][$this->name($node)] = true;
        } elseif ($node instanceof TestExpression) {
            $this->calls['tests'][$this->name($node)] = true;
        } elseif ($node instanceof RangeBinary) {
            $this->calls['functions']['range'] = true;
        }

        $replaced = $node->hasAttribute('sandboxed_function_name') ? $node->getAttribute('sandboxed_function_name') : null;

        if (\is_string($replaced)) {
            $this->calls['functions'][$replaced] = true;
        }
    }

    private function name(Node $node): string
    {
        $name = $node->getAttribute('name');

        return \is_string($name) ? $name : '';
    }

    /**
     * The variables the last template compiled reads without setting them, sorted.
     *
     * @return list<string>
     */
    public function lastNames(): array
    {
        $names = array_keys(array_diff_key($this->read, $this->set));
        sort($names, \SORT_STRING);

        return $names;
    }
}
