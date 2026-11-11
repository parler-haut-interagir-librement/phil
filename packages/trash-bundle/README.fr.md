<!-- phil-dev source-sha256=222c4e8ce8c39c7321e50842bcc880f71bc9cecb889d05d55dcd61a1773d493b -->
# phil/trash-bundle

**Une corbeille pour les pages et les autres ressources supprimées : restaurez-les, ou purgez-les après un délai
fixé.**

phil/trash-bundle donne une corbeille à une application Symfony. Une ressource y entre par un `TrashHandler` que
l'application écrit pour son type. Le gestionnaire garde, en JSON, ce qu'il lui faut pour ramener la ressource, puis
la retire. La ressource revient par le même gestionnaire, sous son parent, ou sous un autre quand son parent n'existe
plus. Une fois sa durée de conservation écoulée, `phil:trash:purge` la supprime pour de bon. Le bundle ne devine pas
comment une entité se reconstruit : chaque type dit quoi garder et comment le restaurer.

Il s'adresse à une application où des rédacteurs suppriment des choses et le regrettent parfois. Un administrateur
liste la corbeille, du plus récent au plus ancien, puis restaure un élément ou le laisse expirer. Chaque étape
déclenche un événement, `ResourceTrashed`, `ResourceRestored` ou `TrashItemPurged` : une piste d'audit peut ainsi
noter qui a fait quoi. La durée de conservation se fixe en jours, pour tous les types ou type par type, ce qui
convient à une politique de conservation des données.

La corbeille elle-même est une table Doctrine, et les ressources sont nommées par `phil/resource`. Le gestionnaire est
le seul port : une corbeille dans une seule table fonctionne pour n'importe quelle entité, avec ses enfants et ses
fichiers, parce que le code qui les connaît appartient à l'application. La ressource quitte sa table et entre dans la
corbeille dans le même flush.

## État

| Partie | État |
|---|---|
| `TrashItem` et le port du dépôt ; `ResourceReference` de `phil/resource` | Implémenté et testé |
| `Trash` : mise à la corbeille, restauration, purge ; `TrashHandler`, `Captured` | Implémenté et testé |
| Événements : `ResourceTrashed`, `ResourceRestored`, `TrashItemPurged` | Implémenté et testé |
| `DoctrineTrashItemRepository` | Implémenté et testé |
| `phil:trash:purge` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, configuration, la table. |
| [Gestionnaires](doc/fr/handlers.md) | Écrire un `TrashHandler`, mettre à la corbeille et restaurer, un parent disparu. |
| [Conservation](doc/fr/retention.md) | La conservation par type, la purge, les événements. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$item = $trash->trash($page, trashedBy: $user->getUserIdentifier());

$trash->restore($item);                                            // under its parent
$trash->restore($item, ResourceReference::of('page', $newParent)); // its parent is gone
```

```bash
bin/console phil:trash:purge
```

## Prérequis

PHP 8.5 ou plus récent avec `ext-mbstring` ; Doctrine ORM 3.7 ; PSR-20 et PSR-14 ; l'injection de dépendances, la
configuration et les UID de Symfony 8.2 ; `phil/resource` 0.1 pour les références de ressources. Symfony Console est
facultatif.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir
[LICENSE](LICENSE).
