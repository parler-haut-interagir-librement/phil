<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Xapi;

/**
 * The lists of components a question of xAPI offers, by the element `tincan.xml` names each with: the choices of a
 * `choice` or `sequencing` question, the scale of a `likert` one, the sources and targets of a `matching` one, the
 * steps of a `performance` one.
 */
enum ComponentList: string
{
    case Choices = 'choices';
    case Scale = 'scale';
    case Source = 'source';
    case Target = 'target';
    case Steps = 'steps';

    /**
     * @return list<self> the lists that interaction type uses, in the order the correct responses name them
     */
    public static function of(?InteractionType $type): array
    {
        return match ($type) {
            InteractionType::Choice, InteractionType::Sequencing => [self::Choices],
            InteractionType::Likert => [self::Scale],
            InteractionType::Matching => [self::Source, self::Target],
            InteractionType::Performance => [self::Steps],
            default => [],
        };
    }
}
