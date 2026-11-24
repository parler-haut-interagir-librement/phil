<!-- phil-dev source-sha256=d2b9452e3433f32ed4ad6ddf22517fec4d5273b3291cf8fdb55df332f1539a4c -->
# Points d'extension

| Point | Comment |
|---|---|
| Qui est le membre | Implémentez `Application\Contract\MemberResolver` et nommez-le dans `member_resolver` |
| Qui sont tous les membres | Implémentez `Application\Contract\MemberSource` et nommez-le dans `member_source` |
| Comment les membres sont avisés | Implémentez `Application\Contract\TermsNotifier` et nommez-le dans `notifier` |
| Ce qui est conservé d'une adresse | Implémentez `Application\Contract\AddressMasker` et créez-en un alias |
| Comment les textes sont vérifiés, affichés et comparés | Implémentez `Application\Contract\PolicyTexts` et créez-en un alias |
| Où les versions sont conservées | Implémentez `Domain\Contract\PolicyVersionRepository` et créez-en un alias |
| Où les acceptations sont conservées | Implémentez `Domain\Contract\AcceptanceRepository` et créez-en un alias |
| Publier et accepter depuis le code | `Application\Terms` : `publish()`, `accept()`, `textMatches()` |
| Ce qu'un membre doit accepter | `Application\AcceptanceChecker::pending()` |
| Qui n'a pas accepté | `Application\Outstanding` : `at()`, `ofLatestMaterial()` |
| Réagir à une publication ou à une acceptation | Écoutez `Application\Event\VersionPublished` et `TermsAccepted` |
| Les routes que la porte laisse passer | `gate.exempt_routes`, `gate.exempt_paths` |
| Les mots des pages | Les messages du domaine `phil_terms`, `policy.<key>` pour chaque titre |
| Les pages | `pages.layout`, `admin.layout`, et les gabarits sous `@PhilTerms/` redéfinis dans `templates/bundles/PhilTermsBundle/` |
| L'apparence des pages | Les recettes du kit que l'application installe — PHIL UX ou Shadcn —, modifiées dans son propre répertoire de composants ; `kit_subdirectory` là où elles se trouvent |
| L'horloge | L'option `clock` : le bundle ne lit jamais l'heure lui-même |

## Les invariants d'un stockage

Un `AcceptanceRepository` :

- ajoute une acceptation, n'en modifie jamais une, et ne les retire que dans `erase()` ;
- répond à `compliant()` pour 500 membres au plus à la fois, à partir des acceptations d'une version dont le numéro est
  au moins celui demandé, au moment donné ou après.

Un `PolicyVersionRepository` refuse une seconde version du même numéro avec une `InvalidTerms` de raison
`version_taken`, et donne les versions d'une politique de la plus récente à la plus ancienne.

## Un notificateur propre à l'application

```php
use Phil\TermsBundle\Application\Contract\TermsNotifier;
use Phil\TermsBundle\Domain\ValueObject\PolicyVersion;

final readonly class MailingListNotifier implements TermsNotifier
{
    public function published(PolicyVersion $version): void { /* announce it to the list */ }

    public function remind(string $policy, int $version, array $members): void { /* 500 members at most */ }
}
```
