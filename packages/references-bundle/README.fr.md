<!-- phil-dev source-sha256=5acdff031479f1792bb3896f3c46b424975154ef4e8a5763f5a0de6499b981e5 -->
# phil/references-bundle

**Sachez où chaque ressource est utilisée, et refusez d'en supprimer une dont quelque chose a encore besoin.**

phil/references-bundle indique à une application Symfony où ses ressources sont utilisées. Un `ReferenceCollector`
qu'écrit l'application trouve les liens d'un genre de source, comme une image dans les blocs JSON d'une page ou une
association polymorphe. Le bundle garde ces liens dans une table, à jour à chaque *flush* de Doctrine. Supprimer une
ressource encore utilisée annule le *flush* avec un `StillReferenced` qui nomme les sources qui l'utilisent.
`phil:references:check` liste les liens vers des ressources disparues, et échoue tant qu'il en reste un.

Il couvre les liens qu'une clé étrangère ne peut pas contenir : une référence dans une colonne JSON, ou un genre et un
identifiant côte à côte. Il s'adresse à une application dont les rédacteurs bâtissent des pages à partir de blocs et de
médias, et doivent voir où paraît une image avant de la retirer. Appeler `References::assertUnused()` avant une
suppression donne un message clair à la personne ; l'écouteur du *flush* est le filet de sécurité derrière.

Le bundle enregistre sa table par Doctrine DBAL et écoute les *flush* de Doctrine ORM. Les ressources sont nommées par
`phil/resource` : des sources et des cibles de tout genre partagent ainsi une seule table. La conception repose sur deux
ports que remplit l'application : les collecteurs disent vers quoi pointe une source, et un `ReferenceTarget` dit si une
ressource de son genre existe encore. Un genre sans cible ne voit jamais une suppression refusée ni ses liens rompus
vérifiés.

## État

| Partie | État |
|---|---|
| `Usage`, le port du dépôt ; `ResourceReference` de `phil/resource` | Implémenté et testé |
| `References` : rafraîchir, oublier, usages, vérification avant suppression, liens rompus ; `ReferenceCollector`, `ReferenceTarget`, `Link` | Implémenté et testé |
| `DbalUsageRepository`, sa table et son écouteur de schéma | Implémenté et testé |
| `ReferenceTrackingListener` : usages à chaque *flush*, suppression refusée | Implémenté et testé |
| `phil:references:check` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement, configuration, la table. |
| [Collecteurs et cibles](doc/fr/collectors.md) | Écrire un collecteur et une cible ; ce que fait le *flush*. |
| [Usages](doc/fr/usages.md) | « Où est-ce utilisé », refuser une suppression, liens rompus. |
| [Points d'extension](doc/fr/extension-points.md) | Ce que l'application implémente, configure, remplace ou utilise. |
| [Tests](doc/fr/testing.md) | Comment le bundle est testé. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle. |

## Démarrage rapide

```php
$references->usagesOf(ResourceReference::of('image', $image->id));   // where is it used?
$references->assertUnused(ResourceReference::of('image', $image->id)); // StillReferenced when it is
```

```bash
bin/console phil:references:check
```

## Prérequis

PHP 8.5 ou plus récent ; Doctrine DBAL 4.5 et ORM 3.7 ; l'injection de dépendances et la configuration de
Symfony 8.2 ; `phil/resource` 0.1 pour les références aux ressources. Symfony Console est facultatif.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la [politique de
sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
