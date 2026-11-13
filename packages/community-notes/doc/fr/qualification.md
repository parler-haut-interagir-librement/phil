<!-- phil-dev source-sha256=c25e6056f34107d74aba91a5ebb438fb99b4d3dec7d0a1af25b33ef298591759 -->
# Qualification

La bibliothèque ne laisse rédiger et évaluer que les membres qualifiés, et demande à l'application qui ils sont par le
port `Phil\CommunityNotes\Contract\Qualification` :

```php
interface Qualification
{
    public function mayWrite(ResourceReference $member, ResourceReference $subject): bool;

    public function mayRate(ResourceReference $member, Note $note): bool;
}
```

`mayWrite()` est demandé par `write()`, avec la ressource dont parle la note ; `mayRate()` par `rate()`, avec la note.
L'auteur d'une note ne l'évalue jamais, quoi que dise la qualification.

## Par Symfony Security

`AuthorizationQualification` demande à Symfony Security, pour le membre connecté, qui est celui qui rédige ou évalue. Il
a besoin de `symfony/security-core`.

```php
use Phil\CommunityNotes\Security\AuthorizationQualification;

new AuthorizationQualification($authorizationChecker);                                         // two attributes
new AuthorizationQualification($authorizationChecker, 'ROLE_NOTE_WRITER', 'ROLE_NOTE_RATER');  // two roles
```

| Demandé par | Attribut, par défaut | Sujet donné aux *voters* |
|---|---|---|
| `mayWrite()` | `COMMUNITY_NOTE_WRITE` | Un `QualificationSubject` : le membre, la ressource, aucune note |
| `mayRate()` | `COMMUNITY_NOTE_RATE` | Un `QualificationSubject` : le membre, la ressource de la note, la note |

Un rôle est décidé par la hiérarchie des rôles, comme tout rôle. Tout autre attribut est décidé par les *voters* de
l'application : un *voter* qui accorde `COMMUNITY_NOTE_RATE` aux membres de six mois, ou dont dix notes ont été jugées
utiles, lit ce dont il a besoin dans `QualificationSubject`.

```php
use Phil\CommunityNotes\Security\AuthorizationQualification;
use Phil\CommunityNotes\Security\QualificationSubject;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class SeniorRaterVoter extends Voter
{
    public function __construct(
        private readonly Members $members,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return AuthorizationQualification::RATE === $attribute && $subject instanceof QualificationSubject;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return $this->members->joinedMonthsAgo($subject->member) >= 6;
    }
}
```

Le vérificateur décide toujours pour l'utilisateur connecté : l'application passe cet utilisateur comme membre.

## Une qualification de l'application

En dehors de Symfony Security, l'application implémente elle-même `Qualification` : un relevé des notes du membre jugées
utiles, un niveau de confiance, une invitation. Elle ne garde aucun état entre les requêtes.
