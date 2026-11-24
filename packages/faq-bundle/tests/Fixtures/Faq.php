<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\Markup\Adapter\DefaultCompiler;
use Phil\Markup\Adapter\SanitizerPurifier;
use Phil\Markup\Markup;
use Phil\Slug\TransliteratingSlugger;
use Psr\Clock\ClockInterface;

/**
 * The bundle's services on an entity manager, as its container builds them.
 */
final class Faq
{
    private function __construct()
    {
    }

    public static function markup(): Markup
    {
        return new Markup(new DefaultCompiler(), SanitizerPurifier::defaultPolicies());
    }

    public static function editor(EntityManagerInterface $entityManager, ClockInterface $clock): FaqEditor
    {
        return new FaqEditor(new DoctrineFaqRepository($entityManager), self::markup(), new TransliteratingSlugger(), $clock);
    }
}
