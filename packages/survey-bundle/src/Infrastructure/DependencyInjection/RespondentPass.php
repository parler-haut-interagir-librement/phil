<?php

declare(strict_types=1);

namespace Phil\SurveyBundle\Infrastructure\DependencyInjection;

use Override;
use Phil\FormsBundle\Application\CurrentSubject;
use Phil\FormsBundle\Infrastructure\Security\TokenCurrentSubject;
use Phil\SurveyBundle\Application\NoRespondent;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * The respondent: the member phil/forms-bundle's `CurrentSubject` gives — the service the application aliases, else
 * the one signed in through Symfony Security —, or nobody without Security.
 */
final readonly class RespondentPass implements CompilerPassInterface
{
    public const string RESPONDENT = 'phil_survey.respondent';

    #[Override]
    public function process(ContainerBuilder $container): void
    {
        if ($container->hasAlias(self::RESPONDENT)) {
            return;
        }

        if (!$container->hasAlias(CurrentSubject::class) && !$container->hasDefinition(CurrentSubject::class)) {
            if ($container->hasDefinition('security.token_storage')) {
                $container->register(TokenCurrentSubject::class, TokenCurrentSubject::class)->setArguments([new Reference('security.token_storage')]);
                $container->setAlias(CurrentSubject::class, TokenCurrentSubject::class);
            } else {
                $container->register(NoRespondent::class, NoRespondent::class);
                $container->setAlias(CurrentSubject::class, NoRespondent::class);
            }
        }

        $container->setAlias(self::RESPONDENT, CurrentSubject::class);
    }
}
