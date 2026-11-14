<!-- phil-dev source-sha256=a7f753245facadcab717723f0ffc6b74aa525e9ae7507865950cd6c11a73b505 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine ORM 3 et son bundle, `phil/resource`, `phil/ujo` et
`phil/notifications-bundle`. Twig 3 pour le filtre. Stimulus 3, AssetMapper et les routes de
`phil/block-editor-bundle` pour le contrôleur.

## Activer le bundle

```sh
composer require phil/mentions-bundle
```

```php
// config/bundles.php
return [
    // …
    Phil\NotificationsBundle\PhilNotificationsBundle::class => ['all' => true],
    Phil\MentionsBundle\PhilMentionsBundle::class => ['all' => true],
];
```

## La recette

La recette Flex du bundle l'enregistre dans `config/bundles.php`, écrit `config/packages/phil_mentions.yaml`, qui
nomme `App\Mentions\Members` comme annuaire, et copie cette classe dans `src/Mentions/Members.php`. Tant que
l'application ne lui fait pas lire ses comptes, elle ne trouve personne : aucun identifiant n'est lié, personne n'est
notifié, la recherche `@` ne liste personne, et l'application démarre. La recette se trouve dans le dépôt de recettes
de PHIL, lu une fois que l'application inscrit ce dépôt dans `extra.symfony.endpoint` ; sans lui, enregistrez le
bundle et écrivez la configuration à la main, comme ci-dessous.

## La table

Le bundle déclare `phil_mention` à Doctrine ; les migrations de l'application la créent :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Colonne | Contient |
|---|---|
| `id` | Un UUID v7 |
| `subject` | Ce qui mentionne le membre, `comment:7` |
| `member` | Le membre mentionné, `user:42` |
| `author` | La personne qui a écrit la mention, `user:9` |
| `created_at` | Le moment du premier enregistrement, à la seconde près |

Un sujet mentionne un membre une seule fois, puisque `(subject, member)` est unique ; `member` est indexé pour trouver
les mentions d'un membre.

## L'annuaire

L'application écrit un seul service, un `MemberDirectory`, et le nomme :

```php
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Member;
use Phil\Resource\ResourceReference;

final readonly class Members implements MemberDirectory
{
    public function __construct(private UserRepository $users, private Access $access) {}

    public function resolve(array $handles): iterable
    {
        foreach ($this->users->withHandles($handles) as $user) {
            yield $this->member($user);
        }
    }

    public function members(array $references): iterable
    {
        foreach ($this->users->withIds(array_map(static fn (ResourceReference $r): string => $r->id, $references)) as $user) {
            yield $this->member($user);
        }
    }

    public function search(string $query, int $limit): iterable
    {
        foreach ($this->users->startingWith($query, $limit) as $user) {
            yield $this->member($user);
        }
    }

    public function maySee(ResourceReference $member, ResourceReference $content): bool
    {
        return $this->access->maySee($member->id, $content);
    }

    private function member(User $user): Member
    {
        return new Member(ResourceReference::of('user', (string) $user->id), $user->handle, $user->name, '/members/'.$user->handle);
    }
}
```

```yaml
# config/packages/phil_mentions.yaml
phil_mentions:
    member_directory: App\Members\Members
```

## Les autres bundles

Chacun est facultatif. Le bundle le configure quand il est activé :

| Bundle | Ce qu'il apporte |
|---|---|
| `phil/block-editor-bundle` | Le menu `@` de l'éditeur cherche dans l'annuaire, et le contrôleur lit cette recherche |
| `phil/comments-bundle` | `CommentMentions`, et `MemberAuthorDirectory` pour nommer les auteurs des commentaires |
| `phil/settings-bundle` | Le réglage `notifications.mentions`, que les membres désactivent |
| `phil/markup-bundle` | Le rendu de Markdown, fait avant que `mentions` change ses identifiants en liens |

Voir [Intégrations](integrations.md).

## Le contrôleur et la feuille de style

Avec AssetMapper et le bundle Stimulus, le contrôleur est servi sous `@phil/mentions-bundle/mention_controller.js` et
la feuille de style sous `phil-mentions/mentions.css`, tous deux inscrits dans la configuration d'AssetMapperBundle ;
sans ce bundle, rien n'est inscrit et le conteneur se compile quand même. Le contrôleur est chargé à la demande,
seulement sur les pages dont le balisage l'utilise :

```json
// assets/controllers.json
{
    "controllers": {
        "@phil/mentions-bundle": {
            "mention": { "enabled": true, "fetch": "lazy" }
        }
    }
}
```

```twig
<link rel="stylesheet" href="{{ asset('phil-mentions/mentions.css') }}">
```
