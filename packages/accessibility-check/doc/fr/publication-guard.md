<!-- phil-dev source-sha256=356e9f791a1f376fe36ecce2c9172eabfd48b5fad1c41e0d462c87e8ddd13651 -->
# Garde de publication

`Workflow\PublicationGuard` est un écouteur des événements de garde de `symfony/workflow` : elle retient une transition
— une publication — tant que le contenu de son sujet n'est pas accessible. La garde demande ce contenu à l'application
par `Contract\SubjectContent` et le vérifie. Elle bloque la transition lorsqu'un constat atteint la sévérité bloquante
ou plus grave, ou lorsque le vérificateur refuse le contenu.

## Le contenu d'un sujet

L'application sait où se trouve le contenu d'un sujet. Pour un contenu de `phil/content-bundle`, le sujet de
`workflow.phil_content.guard.publish` est la ligne de brouillon localisée, dont le corps contient le HTML du brouillon :

```php
use Phil\AccessibilityCheck\Contract\SubjectContent;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\Ujo\Document;

final class DraftBody implements SubjectContent
{
    public function contentOf(object $subject): string|Document|null
    {
        return $subject instanceof BodyCapable ? $subject->getBody()?->html : null;
    }
}
```

`null` laisse le sujet tranquille : une ligne sans corps, un sujet d'un autre type.

## Enregistrer la garde

```yaml
services:
    App\Accessibility\DraftBody: ~

    Phil\AccessibilityCheck\Workflow\PublicationGuard:
        arguments:
            $checker: '@Phil\AccessibilityCheck\AccessibilityChecker'
            $content: '@App\Accessibility\DraftBody'
            $blockAt: !php/enum Phil\AccessibilityCheck\Severity::Error
            $locale: 'fr'
        tags:
            - { name: kernel.event_listener, event: workflow.phil_content.guard.publish }
```

`PublicationGuard::CONTENT_PUBLISH` nomme cet événement. Tout autre événement de garde —
`workflow.<name>.guard.<transition>` — fonctionne de la même façon.

| Argument | Valeur par défaut | Signification |
|---|---|---|
| `checker` | | le vérificateur, avec la politique de l'application |
| `content` | | le `SubjectContent` de l'application |
| `blockAt` | `Severity::Error` | la sévérité à partir de laquelle un constat bloque ; `null` ne bloque jamais |
| `locale` | `en` | la locale du message du bloqueur |
| `language` | `null` | la langue d'un contenu qui n'indique pas la sienne ; celle de la politique si `null` |

## Le bloqueur

Une transition retenue à cause de ses constats porte un `TransitionBlocker` de code `PublicationGuard::BLOCKED`. Son
message — clé `report.blocked` — donne le nombre de constats bloquants et le premier d'entre eux. Ses paramètres
contiennent le `report` au complet, pour l'écran qui indique à l'auteur quoi corriger :

```php
use Phil\AccessibilityCheck\Report;
use Phil\AccessibilityCheck\Workflow\PublicationGuard;

foreach ($workflow->buildTransitionBlockerList($row, 'publish') as $blocker) {
    if (PublicationGuard::BLOCKED === $blocker->getCode()) {
        $report = $blocker->getParameters()['report'];   // a Report
    }
}
```

Un contenu que le vérificateur refuse bloque avec le code `PublicationGuard::REFUSED` et la raison comme message. Une
garde bloquée rend la transition non disponible : avec `phil/content-bundle`, `publish()` lève `TransitionRefused`.
