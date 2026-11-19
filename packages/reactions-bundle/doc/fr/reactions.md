<!-- phil-dev source-sha256=7e0f4df53bb12a26b55050fdf299a7496247a42548013c39fdb130eeced01ada -->
# Réactions

## La liste

Un site offre des réactions dans des sources étiquetées, affichées sous leur titre dans le sélecteur. La configuration
en donne deux : `unicode`, dont le titre est la clé de traduction `source.unicode`, et `site`, `source.site`. L'ordre
est celui de l'option `order`, puis les autres. Une application ajoute une source qui lui est propre, lue dans son
stockage, en implémentant `ReactionSource` ; la configuration automatique l'étiquette. Chaque source est lue une fois
par requête ; au plus 128 réactions, et jamais deux avec le même code.

Une réaction est `quick` quand elle se trouve dans la barre dès le départ. Les autres attendent dans le sélecteur ; dès
que quelqu'un en a choisi une, elle vient dans la barre pour tous ceux qui voient son compteur. Une image déclarée comme
un média qui n'existe plus n'est pas offerte.

Retirer un code de la liste laisse ses lignes dans la table, qui ne sont plus comptées ni affichées.

## Plusieurs réactions par membre

Par défaut, un membre donne une réaction à un contenu, et une nouvelle remplace la précédente. Un site qui en veut plus
dit combien :

```yaml
phil_reactions:
    per_member: 3
```

Chaque membre donne alors chaque code au plus une fois, et au plus trois codes à un même contenu. Une fois le plafond
atteint, les réactions qu'il a données restent enfoncées et les autres sont affichées mais ne peuvent pas être pressées,
le sélecteur fermé, avec une phrase qui dit d'en retirer une d'abord ; une réaction envoyée malgré tout est refusée, le
composant le disant dans sa région active et le simple formulaire répondant `409`. En retirer une, en la pressant de
nouveau, fait de la place pour une autre. Le plafond va de 1 à 20, puisque chaque réaction qu'un membre donne est lue à
chaque page.

Quand deux requêtes dépassent le plafond, ou la réaction unique, en se disputant, le stockage garde les plus anciennes
réactions et retire les plus récentes, si bien que les deux requêtes laissent les mêmes lignes. Abaisser le plafond
garde les réactions déjà données ; un membre au-dessus en retire quelques-unes avant d'en donner une autre.

## Les réactions privées

Un site qui veut que les lecteurs disent qu'une chose n'est pas utile la déclare privée, et n'offre aucune réaction
négative d'une autre façon :

```yaml
phil_reactions:
    unicode:
        - { code: like, emoji: '👍', label: reaction.like }
        - { code: not_useful, emoji: '🫤', label: reaction.not_useful, private: true }
```

Une réaction privée n'a jamais de compteur ni de noms sur une page, peu importe qui regarde. Le membre qui l'a choisie la
voit enfoncée. Elle reste dans le stockage, pour les outils propres à l'application. Comme toute réaction, elle remplace
celle que le membre avait sur ce contenu, ou prend une place de plus sous le plafond.

## La visibilité

| Option | Question | Valeur par défaut |
|---|---|---|
| `panel` | Qui voit les réactions tout court | `everyone` |
| `counts` | Qui voit les compteurs | `everyone` |
| `names` | Qui voit les noms de ceux qui ont réagi | `nobody` |

Chacune répond `everyone`, `members` ou `nobody`. Une `VisibilityPolicy` décide contenu par contenu. Les noms exigent
aussi un service `MemberNames` ; un membre qu'il laisse de côté n'est pas affiché, et un identifiant ne tient jamais
lieu de nom. Au plus dix noms sont affichés pour une réaction, suivis du nombre des autres quand celui qui regarde voit
les compteurs, en une seule liste écrite dans la langue de la page par `format_list` de twig/intl-extra : « Ada, Linus and 3 others ».

Un visiteur n'a pas de réaction propre : il voit ce qui est compté, en simple texte.

## Qui peut réagir

L'attribut `Reactions::REACT`, `phil_reactions.react`, est demandé au sujet d'un `ReactionSubject` : le contenu, le
membre et la définition de la réaction. Si aucun *voter* ne répond, Symfony refuse, et sans Security personne ne peut
réagir. Un *voter* peut réserver une réaction, par exemple les émojis d'un site pour ses abonnés :

```php
protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
{
    \assert($subject instanceof ReactionSubject);

    return 'gnome' !== $subject->reaction->code || \in_array('ROLE_SUBSCRIBER', $token->getRoleNames(), true);
}
```

Une réaction que le *voter* refuse n'est pas offerte. Un membre peut toujours retirer la réaction qu'il a, même une fois
qu'elle est refusée.

## Le membre

Le membre est un identifiant, gardé dans la table et porté par les événements. Le site le donne, par le service
`CurrentMember` qu'il nomme dans `current_member` : un identifiant stable qui ne change jamais et ne nomme personne
d'autre après lui, comme l'identifiant d'un compte ou la revendication `sub` d'un fournisseur d'identité, et jamais une
adresse courriel ni un nom d'utilisateur.

Avec Symfony Security, `current_member` est obligatoire : le conteneur n'est pas construit sans lui, et son message le
dit. `TokenCurrentMember` donne l'identifiant d'utilisateur du jeton de sécurité, pour un site dont les identifiants
d'utilisateur sont stables :

```yaml
phil_reactions:
    current_member: Phil\ReactionsBundle\Infrastructure\Security\TokenCurrentMember
```

Un identifiant qui contient `@` est refusé, quel que soit le service qui le donne : `TokenCurrentMember` lance
`InvalidReaction` plutôt que de le donner, et aucune réaction n'est gardée sous lui. Le message omet l'identifiant,
puisqu'il peut arriver dans un journal. Sans Security, `NoCurrentMember` fait de chacun un visiteur.

## La limite de débit

`react()` et `withdraw()` sont soumis au limiteur par membre, une fois que l'accès a répondu. Le composant le dit dans
sa région active ; le simple formulaire répond `429` avec `Retry-After`.
