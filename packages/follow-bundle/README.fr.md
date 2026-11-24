<!-- phil-dev source-sha256=615a442e39bb14ddf37e8dcfe94acf2878117a5aad71b829e8aef262f1ad5bba -->
# phil/follow-bundle

**Suivez un contenu, un fil, une annonce ou un membre au niveau que chaque membre choisit, et apprenez les nouveautés par
phil/notifications-bundle : demandes pour les profils verrouillés, relations mutuelles, abonnés cachés sur demande.**

phil/follow-bundle est un bundle Symfony. Un membre suit un sujet — tout enregistrement de l'application, nommé par un
type et un identifiant — à l'un de quatre niveaux : **surveillance**, **mentions seulement**, **ignorance** ou
**personnalisé**, un niveau pour chaque sorte d'événement. Suivre un parent, c'est suivre ses enfants, sauf si le membre
a choisi autrement sur un sujet plus proche. Quand l'application publie une activité, le bundle l'envoie aux abonnés par
`phil/notifications-bundle`, une page bornée à la fois, par des messages de Messenger qui ne portent que des
identifiants, et jamais deux fois au même membre.

```php
$follows->follow($member, new SubjectReference('thread', $thread->id));
$activities->publish(new SubjectReference('thread', $thread->id), 'reply.created', $author, $mentioned, 'reply:'.$reply->id);
```

## Fonctionnalités

- **Niveaux** : surveillance, mentions seulement, ignorance — qui arrête aussi ce qu'apporte un parent suivi —, et
  personnalisé par événement ; voir [Suivre](doc/fr/following.md).
- **Héritage** : l'application nomme les parents d'un sujet ; le suivi le plus proche décide.
- **Demandes** : un profil verrouillé, ou tout sujet que l'application soumet à approbation, transforme un suivi en
  demande que son approbateur accepte ou rejette — `Follow`, `Accept` et `Reject`, les mots d'ActivityPub.
- **Relations mutuelles**, activées par la configuration, à côté du suivi à sens unique ; cesser de suivre les
  conserve.
- **Livraison** par `phil/notifications-bundle` : des pages d'abonnés, des revendications par membre et par activité
  pour qu'une nouvelle tentative n'envoie à personne deux fois, une notification sur tous les canaux jusqu'à ce que le
  membre voie le sujet, et le résumé du bundle de notifications ; voir [Livraison](doc/fr/delivery.md).
- **Suivis automatiques**, quand ils sont activés, de ce qu'un membre crée ou de ce à quoi il répond.
- **Vie privée** : abonnés cachés au choix de chaque membre, jamais montrés par l'API ni par les gabarits ; export et
  effacement par phil/privacy-bundle ; voir [Vie privée](doc/fr/privacy.md).
- **Gabarits** qui fonctionnent sans JavaScript : le bouton de suivi et son menu de niveaux, les demandes, les abonnés,
  la visibilité de ses abonnés, la relation mutuelle ; voir [Gabarits](doc/fr/templates.md).
- Aucun fil d'activité : chaque application compose sa page d'accueil à partir des suivis et des notifications ; voir
  [Une page d'accueil](doc/fr/home-page.md).

## État

| Partie | État |
|---|---|
| Suivis, niveaux, préférences par événement, héritage | Implémenté et testé |
| Demandes, relations mutuelles, suivis automatiques | Implémenté et testé |
| Livraison par pages, revendications, retenue jusqu'à la consultation, calendrier de purge | Implémenté et testé |
| Visibilité des abonnés, export et effacement | Implémenté et testé |
| Gabarits et formulaires sans JavaScript | Implémenté et testé |
| Recette Flex | Pas encore écrite |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les tables et les routes. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Suivre](doc/fr/following.md) | Niveaux, préférences par événement, héritage, demandes, relations mutuelles, suivis automatiques. |
| [Livraison](doc/fr/delivery.md) | Publier une activité, les pages, les revendications, la retenue jusqu'à la consultation, le résumé et la purge. |
| [Gabarits](doc/fr/templates.md) | Les gabarits, leurs fonctions Twig et leurs formulaires. |
| [Une page d'accueil](doc/fr/home-page.md) | Comment une application compose la page d'accueil d'un membre à partir des suivis et des notifications. |
| [Vie privée](doc/fr/privacy.md) | Les abonnés cachés, l'export et l'effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports que l'application écrit et les services qu'elle appelle. |
| [Tests](doc/fr/testing.md) | Les tests du bundle. |
| [Référence](doc/fr/reference.md) | Chaque type public du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Phil\FollowBundle\PhilFollowBundle::class => ['all' => true],
```

```yaml
# config/packages/phil_follow.yaml
phil_follow:
    access: App\Follow\SubjectAccess   # required with Symfony Security
```

```yaml
# config/routes/phil_follow.yaml
phil_follow:
    resource: '@PhilFollowBundle/config/routes.php'
    prefix: /follow
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 ou une version plus récente, Doctrine DBAL 4.5 ou une version plus
récente et phil/notifications-bundle 0.3 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
