<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application\Content;

use Override;
use Twig\Environment;
use Twig\Error\SyntaxError;
use Twig\Node\Expression\Binary\RangeBinary;
use Twig\Node\Node;
use Twig\NodeVisitor\NodeVisitorInterface;

/**
 * Refuses, when a sandboxed template compiles, the range operator `1..1000000`: the only way a template could loop
 * more times than its data has items. Loops over the lists a message is given stay allowed.
 */
final readonly class RangeRefusal implements NodeVisitorInterface
{
    #[Override]
    public function enterNode(Node $node, Environment $env): Node
    {
        if ($node instanceof RangeBinary) {
            throw new SyntaxError('A template of the newsletter cannot use the range operator "..".', $node->getTemplateLine(), $node->getSourceContext());
        }

        return $node;
    }

    #[Override]
    public function leaveNode(Node $node, Environment $env): Node
    {
        return $node;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }
}
