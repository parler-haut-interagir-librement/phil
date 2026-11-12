<!-- phil-dev source-sha256=d5c03a1bcb3b1c0e49c3653cb4ca3eb011506f709971d5499ccd5d07b234f65f -->
# Exceptions

Chaque exception de la bibliothèque implémente `Phil\IpAnonymizer\Exception\IpAnonymizerException` et étend
`InvalidArgumentException`. **Aucun de leurs messages ne répète une adresse** : une adresse est un renseignement
personnel, et les messages d'exception aboutissent dans les journaux.

| Exception | Lancée quand |
|---|---|
| `InvalidIpAddress` | `IpAddress::parse()` reçoit un texte qui n'est pas une adresse, `fromBytes()` ni 4 ni 16 octets. |
| `InvalidNetwork` | Une notation CIDR est mal formée ou a des bits d'hôte allumés, une longueur de préfixe sort de sa famille, une table de libellés liste un réseau deux fois ou a un libellé vide ou trop long. |
| `InvalidSalt` | L'identifiant d'un sel ou sa longueur sort des bornes, une clé maîtresse ne compte pas 32 octets. |
| `InvalidOption` | Une longueur d'empreinte sort de l'intervalle de 16 à 64 octets, une période tournante est plus courte qu'une seconde, l'horloge lit un instant antérieur à l'époque Unix. |

`IpAddress::tryParse()` retourne `null` au lieu de lancer une exception : c'est la forme à utiliser pour une adresse lue
dans une requête ou une ligne de journal.

Les paramètres qui reçoivent une adresse, un sel ou une clé maîtresse portent `#[SensitiveParameter]`, pour qu'une trace
de pile ne les montre pas.
