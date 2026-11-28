<!-- phil-dev source-sha256=cef53ac033717f4e9820d1070db6a6dee353c150ca5f6c02b9b8a9367a4bc94e -->
# phil/audience-segments

**Choisissez qui reçoit un message par des règles JSON lisibles sur les billets, les achats, le soutien et les
étiquettes, avec des listes de destinataires figées et des exclusions globales, sans profilage.**

phil/audience-segments est une bibliothèque PHP. Un segment est une règle nommée de [phil/rules](../rules/README.fr.md)
sur les faits d'un membre de l'audience — détient un billet d'un événement, a participé à une date, a été choisi par un
organisateur, a acheté un SKU, soutient un palier, porte une étiquette interne —, écrite dans un langage de requête JSON
que partagent une API et un éditeur visuel.

- **Segments dynamiques**, calculés à partir des faits tels qu'ils sont maintenant, lus par pages bornées, ou
  recalculés selon un horaire avec un événement `MemberEntered` ou `MemberLeft` pour chaque changement.
- **Listes figées** : les membres exacts à une date, en identifiants opaques, avec l'empreinte de la règle, conservées
  avec un message comme preuve de ses destinataires.
- **Exclusions globales** : les membres désabonnés et les refus sont retirés de chaque segment avant la lecture de sa
  règle.
- **Faits par des ports** qu'implémentent les modules de billetterie, d'événements, de boutique, de soutien et de
  personnes : la bibliothèque ne lit aucune table qui lui soit propre et n'a besoin d'aucune plateforme externe de
  données clients.
- **Aucun profilage** : aucun trait deviné, aucun score de comportement, aucun filtre selon qu'un message a été ouvert
  ou cliqué. Un segment refuse les types de condition dont le nom l'indique.

Les événements et les messages ne portent que des identifiants, jamais une adresse ni un nom.

## État

| Partie | État |
|---|---|
| `Segment`, `SegmentJson` : le langage de requête, lu, écrit, avec empreinte | Implémenté et testé |
| Les types de condition d'audience et leurs ports | Implémenté et testé |
| `Segments` : un membre avec la raison, pages bornées, listes figées | Implémenté et testé |
| `Recalculation` : membres stockés, événements d'entrée et de sortie, date de la dernière exécution | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, le branchement des ports. |
| [Segments](doc/fr/segments.md) | Le langage de requête, les segments dynamiques et les listes figées, les exclusions, le recalcul, ce qui est refusé. |
| [Points d'extension](doc/fr/extension-points.md) | Les ports, avec un exemple implémenté, et ce qui est stable. |
| [Tests](doc/fr/testing.md) | Les tests et le banc d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require phil/audience-segments
```

```php
use Phil\AudienceSegments\AudienceConditions;
use Phil\AudienceSegments\SegmentJson;
use Phil\AudienceSegments\Segments;

$json = new SegmentJson(AudienceConditions::types(participation: $participation, tags: $tags));
$segment = $json->read('fair-2027-buyers', '{"version": 1, "name": "Spring fair buyers", "condition": {"any": [
    {"type": "ticket.holder", "operands": {"event": "spring-fair-2027"}},
    {"type": "person.tagged", "operands": {"tags": ["press"]}}
]}}');

$segments = new Segments($subscribers, $exclusions, $clock);
$page = $segments->page($segment, after: null, limit: 100); // members now, without the excluded
$list = $segments->freeze($segment);                        // the recipients of one message, kept with it
```

## Prérequis

PHP 8.5 ou une version plus récente, phil/rules, et les interfaces PSR d'horloge et de répartiteur d'événements.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Parler Haut Interagir Librement project. Voir [LICENSE](LICENSE).
