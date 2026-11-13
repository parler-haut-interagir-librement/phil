<!-- phil-dev source-sha256=0865b50eeb618c17cbf80b726584d8aa498ba7cbcda0f966f91a5a4285ca0fef -->
# Groupes d'accès

Un groupe d'accès rassemble des membres qui obtiennent le même accès : les soutiens d'un projet, les participants
d'une cohorte, les nouveaux membres d'un site. Chacun est déclaré dans la configuration avec les points d'entrée qu'il
accepte et, quand ses adhésions se terminent d'elles-mêmes, leur durée :

```yaml
phil_access:
    groups:
        supporters: { entry_points: [purchase, invitation], duration: P1M }
        cohort-2027: { entry_points: [bulk, automation], duration: P6M }
        newcomers: { entry_points: [default] }
```

| Point d'entrée | Un membre arrive |
|---|---|
| `purchase` | en achetant un produit, une offre, un palier de don |
| `invitation` | par une invitation personnelle ou un lien d'invitation |
| `bulk` | ajouté par un administrateur, seul ou avec beaucoup d'autres |
| `automation` | par une règle du site : une automatisation, un flux de travail |
| `default` | comme nouveau membre, dès sa première visite |

`Application\AccessGroups` fait entrer et sortir les membres :

```php
$until = $groups->join('supporters', MemberId::of('01JABC'), EntryPoint::Purchase); // ?DateTimeImmutable
$groups->leave('supporters', MemberId::of('01JABC'));
$groups->joinDefaults(MemberId::of('01JNEW'));     // every group with the default entry point
$groups->groupsOf(MemberId::of('01JABC'));         // list<string>
$groups->members('supporters', limit: 100);        // list<Membership>
```

Un point d'entrée que le groupe n'accepte pas est refusé. Entrer de nouveau fait recommencer la durée : un achat
renouvelé prolonge l'accès. Entrer envoie `MemberJoinedGroup` et sortir `MemberLeftGroup`, avec des identifiants
seulement ; une adhésion qui se termine d'elle-même ne déclenche aucun événement.

Un groupe est ensuite un bénéficiaire comme un membre : un élément partagé avec lui, un espace dont il est membre ou
administrateur.
