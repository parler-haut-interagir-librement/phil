<!-- phil-dev source-sha256=811d973dbf7f4b8780e2f790f621d03691e9d41e19bc657482e4a471fccb1e1e -->
# Installation

## Prérequis

| Paquet | Version | Raison |
|---|---|---|
| PHP | 8.5 ou plus récent | Classes en lecture seule, constantes typées, `new` sans parenthèses. |
| `ext-sodium` | fournie avec PHP | Les empreintes BLAKE2b à clé, la dérivation de clés des sels tournants, l'hexadécimal en temps constant. |
| `psr/clock` | `^1.0` | Les sels d'une période la lisent sur une horloge PSR-20. |

## Installer

```sh
composer require phil/ip-anonymizer
```

## Choisir une stratégie

| Votre besoin | Stratégie | Exemple de ce qui est conservé |
|---|---|---|
| Une valeur qui se lit encore comme une adresse, pour des outils faits pour les adresses | `Truncation` | `203.0.113.0`, `2001:db8:1234::` |
| Compter les visiteurs ou limiter le débit des clients sans conserver leur adresse | `KeyedHash` avec `ForgottenSalt` | `86400-20400:5f0c9b…` |
| Montrer quelle part de l'adresse a été conservée | `NetworkGeneralisation` | `203.0.113.0/24` |
| Savoir seulement si le client est local, privé ou public | `ScopeGeneralisation` | `public` |
| Une région ou un site qui vous est propre, sans base de géolocalisation | `LabelGeneralisation` sur `NetworkLabels` | `office` |

Les stratégies se composent : voir [Stratégies](strategies.md#composer-les-stratégies).

## Les construire une seule fois

Chaque classe est immuable et ne conserve aucun état entre deux appels. Construisez les stratégies une seule fois (comme
services d'un conteneur, par exemple) et partagez-les entre les requêtes d'un worker de longue durée. `NetworkLabels` et
`ScopeClassifier` construisent leurs tables de recherche dans leur constructeur : les construire une seule fois épargne
donc aussi ce travail.
