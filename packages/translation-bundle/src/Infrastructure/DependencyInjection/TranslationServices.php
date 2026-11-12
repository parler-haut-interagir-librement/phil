<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Infrastructure\DependencyInjection;

use Phil\TranslationBundle\Application\Comments;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository;
use Phil\TranslationBundle\Domain\Repository\SegmentRepository;
use Phil\TranslationBundle\Domain\Repository\TermRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineCommentRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineMemoryStore;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineSegmentRepository;
use Phil\TranslationBundle\Infrastructure\Doctrine\DoctrineTermRepository;
use Phil\TranslationBundle\Infrastructure\Http\GlossaryController;
use Phil\TranslationBundle\Infrastructure\Http\SameOriginGuard;
use Phil\TranslationBundle\Infrastructure\Http\TranslationController;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryCommentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository;
use Phil\TranslationBundle\Infrastructure\Twig\CreditsExtension;
use Phil\TranslationMemory\Glossary\RegularPlurals;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\MemoryStore;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\Variants;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The bundle's services: the stores of segments, terms, comments and memory, the segmenter, the regional variants, the
 * memory, the glossaries, the comments, the translations, the check of the screen's forms on FrameworkBundle's CSRF
 * token manager, the screen's and the glossary's controllers, and the Twig function of the translators' credit.
 */
final class TranslationServices
{
    private function __construct()
    {
    }

    /**
     * @param array{store: string, entity_manager: string, granularity: string, prefill: bool, min_score: float, max_suggestions: int, sources: ?string, policy: ?string, layout: string, variants: list<string>, translators: ?string, inflector: ?string, machine_translation: array{enabled: bool, translator: ?string}} $config
     */
    public static function register(ContainerBuilder $container, array $config): void
    {
        if ('memory' === $config['store']) {
            $container->register(InMemorySegmentRepository::class, InMemorySegmentRepository::class);
            $container->register(InMemoryTermRepository::class, InMemoryTermRepository::class);
            $container->register(InMemoryStore::class, InMemoryStore::class);
            $container->register(InMemoryCommentRepository::class, InMemoryCommentRepository::class);
            $container->setAlias(CommentRepository::class, InMemoryCommentRepository::class)->setPublic(true);
            $container->setAlias(SegmentRepository::class, InMemorySegmentRepository::class)->setPublic(true);
            $container->setAlias(TermRepository::class, InMemoryTermRepository::class)->setPublic(true);
            $container->setAlias(MemoryStore::class, InMemoryStore::class)->setPublic(true);
        } else {
            $entityManager = new Reference('doctrine.orm.'.$config['entity_manager'].'_entity_manager');
            $container->register(DoctrineSegmentRepository::class, DoctrineSegmentRepository::class)->setArguments([$entityManager]);
            $container->register(DoctrineTermRepository::class, DoctrineTermRepository::class)->setArguments([$entityManager]);
            $container->register(DoctrineMemoryStore::class, DoctrineMemoryStore::class)->setArguments([$entityManager, new Reference('clock')]);
            $container->register(DoctrineCommentRepository::class, DoctrineCommentRepository::class)->setArguments([$entityManager]);
            $container->setAlias(CommentRepository::class, DoctrineCommentRepository::class)->setPublic(true);
            $container->setAlias(SegmentRepository::class, DoctrineSegmentRepository::class)->setPublic(true);
            $container->setAlias(TermRepository::class, DoctrineTermRepository::class)->setPublic(true);
            $container->setAlias(MemoryStore::class, DoctrineMemoryStore::class)->setPublic(true);
        }

        $container->register(UjoSegmenter::class, UjoSegmenter::class)
            ->setArguments([Granularity::from($config['granularity'])]);
        $container->register(Variants::class, Variants::class)
            ->setArguments([$config['variants']]);
        $container->register(RegularPlurals::class, RegularPlurals::class);
        $container->register(TranslationMemory::class, TranslationMemory::class)
            ->setArguments([new Reference(MemoryStore::class), $config['min_score'], $config['max_suggestions'], 50, new Reference(Variants::class)])
            ->setPublic(true);
        $container->register(Glossaries::class, Glossaries::class)
            ->setArguments([new Reference(TermRepository::class), self::service($config['inflector'])])
            ->setPublic(true);
        $container->register(Comments::class, Comments::class)
            ->setArguments([new Reference(CommentRepository::class), new Reference('clock'), self::service($config['translators'])])
            ->setPublic(true);
        $container->register(Translations::class, Translations::class)
            ->setArguments([
                new Reference(SegmentRepository::class),
                new Reference(TranslationMemory::class),
                new Reference(Glossaries::class),
                new Reference(UjoSegmenter::class),
                new Reference('clock'),
                $config['prefill'],
                new Reference(Variants::class),
                self::service($config['translators']),
                $config['machine_translation']['enabled'] ? self::service($config['machine_translation']['translator']) : null,
                '$policy' => self::service($config['policy']),
            ])
            ->setPublic(true);
        $container->register(SameOriginGuard::class, SameOriginGuard::class)
            ->setArguments([new Reference('security.csrf.token_manager')]);
        $container->register(TranslationController::class, TranslationController::class)
            ->setArguments([
                new Reference(Translations::class),
                new Reference(Comments::class),
                new Reference('twig'),
                new Reference('router'),
                new Reference(SameOriginGuard::class),
                $config['layout'],
                self::service($config['sources']),
                self::service($config['policy']),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(GlossaryController::class, GlossaryController::class)
            ->setArguments([
                new Reference(Glossaries::class),
                new Reference('twig'),
                new Reference('router'),
                new Reference(SameOriginGuard::class),
                $config['layout'],
                self::service($config['policy']),
            ])
            ->addTag('controller.service_arguments')
            ->setPublic(true);
        $container->register(CreditsExtension::class, CreditsExtension::class)
            ->setArguments([new Reference(Translations::class)])
            ->addTag('twig.attribute_extension')
            ->addTag('twig.runtime');
    }

    private static function service(?string $id): ?Reference
    {
        return null === $id ? null : new Reference($id);
    }
}
