<!-- phil-dev source-sha256=25bd376af886ed50593fdd986555c3321e58e61dee02a7fe5437e010ad7b04f4 -->
# Points d'extension

La bibliothèque est du PHP simple et ne déclare aucun service. Ce qu'une application ajoute, ce sont des services de
messagerie.

## `MessengerType`

`Phil\InstantMessenger\MessengerType` : un service est un cas d'une enum qui l'implémente. Voir [Services](services.md).

| Méthode | Contrat |
|---|---|
| `normalize(string $input): ?string` | L'identifiant canonique que représente l'entrée (un nom, une adresse, un numéro de téléphone sous la forme du service), ou `null` quand elle n'en est pas un ; ne lance jamais d'exception |
| `display(string $handle): string` | L'identifiant canonique tel que les gens le lisent |
| `link(string $handle): ?string` | L'adresse qui ouvre une conversation, ou `null` quand le service n'en a pas |

Un forum communautaire, un réseau régional : une enum propre à l'application, lue et affichée à sa façon, acceptée à
côté de `KnownMessenger`.

## Détection

| Point | Comment |
|---|---|
| Les services acceptés | Les `EnumSet` passés à `Messengers::detect()` et à `Messengers::handle()` |
| Le service qui l'emporte | L'ordre des ensembles, puis l'ordre de déclaration des cas de chaque enum |
| Un identifiant déjà canonique | `Handle::fromCanonical()`, pour les valeurs relues depuis le stockage |

Un service est conservé par sa classe d'enum et son cas partout où un identifiant est stocké avec lui : renommer ou
retirer un cas que les données nomment encore exige une migration des données.
