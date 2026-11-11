<?php

declare(strict_types=1);

namespace Phil\SlugBundle\Infrastructure\DependencyInjection;

use Phil\Slug\InvalidSlug;
use Phil\Slug\LeadingWordsRemoved;
use Phil\Slug\Slug;
use Phil\Slug\Slugger;
use Phil\Slug\Sluggers;
use Phil\Slug\TransliteratingSlugger;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers the named sluggers: one service per name, `phil_slug.slugger.<name>`, autowired as
 * `Slugger $<name>Slugger`, the default one as `Slugger`, and all of them in `Sluggers`.
 */
final class SluggerServices
{
    /** The service id of the slugger of a name: `phil_slug.slugger.<name>`. */
    public const string ID_PREFIX = 'phil_slug.slugger.';

    /** The service id of every slugger by name, `Sluggers`. */
    public const string SLUGGERS_ID = Sluggers::class;

    /** The longest slug a slugger can be given to make. */
    public const int MAX_LENGTH = Slug::MAX_LENGTH;

    /**
     * @param array<string, array{locale: ?string, max_length: int, leading_words: list<string>}> $sluggers
     */
    public static function register(ContainerBuilder $builder, array $sluggers, string $default): void
    {
        $references = [];

        foreach ($sluggers as $name => $options) {
            $id = self::ID_PREFIX.$name;
            $slugger = new Definition(TransliteratingSlugger::class, [$options['locale'], $options['max_length']]);

            if ([] !== $options['leading_words']) {
                $slugger = new Definition(LeadingWordsRemoved::class, [$slugger, $options['leading_words']]);
            }

            $builder->setDefinition($id, $slugger);
            $builder->registerAliasForArgument($id, Slugger::class, $name.'Slugger');
            $references[$name] = new Reference($id);
        }

        $builder->setAlias(Slugger::class, self::ID_PREFIX.$default);
        $builder->setDefinition(self::SLUGGERS_ID, new Definition(Sluggers::class, [$references, $default]))->setPublic(true);
    }

    /**
     * Whether `LeadingWordsRemoved` takes the word, so that a word it would refuse fails the container, not a request.
     */
    public static function isLeadingWord(mixed $word): bool
    {
        try {
            return \is_string($word) && $word === LeadingWordsRemoved::checkWord($word);
        } catch (InvalidSlug) {
            return false;
        }
    }
}
