<!-- phil-dev source-sha256=0e623e870ae5532f40379702b47aa2fb2beb7e04d27be88fc6fbb594fbbca3cb -->
# phil/benefit-deliverables

**Les avantages dus aux membres à chaque période, en liste de contrôle, avec des exports de livraison minimaux qui
expirent, pour les abonnements, les adhésions et les dons.**

phil/benefit-deliverables transforme les avantages d'un palier en éléments à livrer : chaque avantage dû à chaque membre
pour une période, une carte postale, une mention dans le générique, une partie avec le meneur de jeu, est une ligne
d'une liste de contrôle. L'équipe coche les lignes une à une ou par lot, et lit qui reste à servir. Pour un avantage qui
expédie un objet, elle écrit l'export de livraison : un fichier CSV qui ne contient que ce dont le livreur a besoin,
consigné dans un journal, téléchargé par un lien signé, et effacé à son expiration.

Il s'adresse à un site dont les membres le soutiennent par paliers, par abonnement, adhésion ou don, et à qui il doit
quelque chose en retour à chaque période.

C'est une bibliothèque PHP simple. Qui se voit dû quoi, ce qui a été livré, où habite chaque membre et où les fichiers
attendent sont des ports que l'application implémente : le paquet ne dépend d'aucun module d'abonnements et ne garde
aucune adresse. Le fichier CSV est écrit par `phil/spreadsheet`, qui garde comme du texte une valeur qu'un tableur
exécuterait comme une formule.

## État

| Partie | État |
|---|---|
| Les avantages dus par période, lus par un port | Implémenté et testé |
| La liste de contrôle : les lignes par membre, cochées une à une ou par lot, qui reste à servir | Implémenté et testé |
| L'export de livraison : CSV minimal, consigné, lien signé, fichier effacé à l'expiration | Implémenté et testé |
| Les fichiers dans un répertoire local, `0600` | Implémenté et testé |
| Le port de l'API d'un transporteur | Interface seulement ; aucun adaptateur |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [La liste de contrôle](doc/fr/checklist.md) | Les périodes, les avantages, les droits, les lignes, les marquer faites, qui reste à servir. |
| [Exports de livraison](doc/fr/shipping-exports.md) | Ce que le fichier contient et ne contient jamais, le journal, le lien signé, l'expiration, la purge. |
| [Transporteurs](doc/fr/carriers.md) | Confier un élément à livrer à un transporteur par son API. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports qu'une application implémente, et quand ils sont stables. |
| [Tests](doc/fr/testing.md) | Les tests du paquet et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/benefit-deliverables
```

```php
use Phil\BenefitDeliverables\Checklist;
use Phil\BenefitDeliverables\Period;

$checklist = new Checklist($entitlementSource, $deliverableStore);
$page = $checklist->remaining(Period::of('2026-11'), 'postcard');

foreach ($page->items as $item) {
    // $item->key->member is still owed a postcard for November.
}

$checklist->markDone(Period::of('2026-11'), 'postcard', ['member-1', 'member-2'], $now, 'staff-7');
```

## Prérequis

PHP 8.5 ou une version plus récente avec `mbstring` et `sodium` ; `phil/address` et `phil/spreadsheet` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
