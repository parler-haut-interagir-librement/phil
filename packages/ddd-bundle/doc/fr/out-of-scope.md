<!-- phil-dev source-sha256=84701ae39281913d394f531143ad1fc4a4146efc0087bf1571213400af15ccac -->
# Ce que le bundle ne contient pas, volontairement

Le bundle est le code que partagent tous les contextes délimités de toutes les applications. Chaque ajout est une
dépendance qu'ils acquièrent tous d'un coup, et un changement incompatible coûte une version majeure à chacun d'eux.
Il reste donc volontairement minuscule, et ce qui suit est laissé de côté exprès.

| Laissé de côté | Pourquoi | Quoi faire à la place |
|---|---|---|
| **Une classe de base d'agrégat** | Un agrégat est une classe de son contexte. Une classe de base partagée fond tous les contextes en un seul dès qu'on y met la première méthode utile | Écrivez chaque agrégat comme une simple classe de son contexte |
| **Une interface `Clock`** | Une interface qui ne ferait qu'étendre PSR-20 n'ajouterait rien qu'un nom | Dépendez directement de `Psr\Clock\ClockInterface` ; `symfony/clock` l'implémente |
| **L'*event sourcing*** | Les événements avertissent, ils ne reconstituent pas : aucun magasin d'événements, aucun rejeu | Stockez l'état comme état ; voir [domain-events.md](domain-events.md) |
| **Les déclarations des bus Messenger** | Les *middlewares* de chaque bus (une transaction sur les commandes, aucune sur les requêtes) sont une décision de l'application | Déclarez les bus dans `messenger.yaml` ; voir [installation.md](installation.md) |
| **Une présentation publique des identifiants** | `Identifier` est la représentation interne ; ce qui sort du système a une forme que décide l'application | Faites correspondre les identifiants à des jetons, des *slugs* ou des références opaques dans l'application |
| **Les objets-valeurs métier** (une adresse courriel, une locale…) | Leur normalisation et leurs valeurs acceptées sont des politiques propres à chaque application | Écrivez-les dans l'application, sur `InvalidValue` |
| **Un validateur dans le domaine** | Une liste de violations sert aux formulaires ; un objet-valeur existe ou n'existe pas | Validez dans l'objet-valeur, levez `InvalidValue` |

## Pas encore commencé

Ces éléments ont leur place dans le bundle et ne sont pas dans le code aujourd'hui :

- **La répartition des événements du domaine après le *flush* de Doctrine**, dans la transaction du *handler* et avant
  son commit : une interface qu'implémente l'agrégat pour enregistrer ses événements, et un écouteur Doctrine qui les
  libère sur un bus d'événements. Voir [domain-events.md](domain-events.md) pour quoi faire d'ici là.
- **Une configuration documentée de boîte de réception Messenger** pour les consommateurs d'événements d'intégration,
  écrite comme documentation plutôt que comme code.
