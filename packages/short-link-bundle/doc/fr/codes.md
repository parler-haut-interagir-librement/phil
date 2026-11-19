<!-- phil-dev source-sha256=db7e3f3f32de2d1527fd212cd5ab4fd8c0b3664df68266a222128d79da94df26 -->
# Codes

Un code est le chemin d'un lien court : `https://go.example.com/Xk3mQp`.

## Le format Sqids

Un code tiré est un nombre aléatoire écrit dans le format de la spécification [Sqids](https://sqids.org). Le même
alphabet, la même longueur minimale et la même liste de blocage donnent le même identifiant que les autres
implémentations du format : un code peut donc être décodé par un autre programme qui connaît la configuration.
`Application\Code\SqidCodec` implémente le format :

```php
use Phil\ShortLinkBundle\Application\Code\SqidCodec;

$codec = new SqidCodec(minLength: 6);
$codec->encode([1, 2, 3]); // "86Rf07"
$codec->decode('86Rf07');  // [1, 2, 3]
```

Un identifiant n'est pas un secret : il cache l'ordre des nombres, rien de plus. Le nombre d'un code tiré est
aléatoire, de sorte qu'un code ne dit rien des liens créés avant ou après lui, et que le suivant ne peut pas être
deviné.

## L'alphabet

Les caractères des codes, chacun une seule fois, parmi ceux qu'un chemin d'URL porte sans échappement : les lettres,
les chiffres, `-`, `.`, `_` et `~`, au moins trois. Un autre alphabet donne d'autres codes : une application qui
mélange une fois l'alphabet par défaut, et le garde, a des codes bien à elle.

## La liste de blocage

Un code tiré évite les mots de la liste de blocage : celui qui contient un mot, qui commence ou finit par un mot qui a
un chiffre, ou qui est un mot de trois caractères, est remplacé par l'identifiant suivant que donne le format. Un mot
n'est gardé que s'il compte trois caractères ou plus, tous dans l'alphabet ; il est comparé en minuscules. La liste par
défaut contient des mots grossiers en anglais et en français ; `code.blocklist` en ajoute d'autres, et
`code.default_blocklist: false` abandonne la liste par défaut.

## Les codes tirés et les codes choisis

Un code laissé vide est tiré : un nombre aléatoire de 0 à `code.max_number`, écrit avec au moins `code.min_length`
caractères. Un code déjà pris est tiré de nouveau, huit fois au plus ; ensuite la création est refusée, et un
`max_number` plus grand donne plus de place.

Quand `code.custom` le permet, un auteur peut choisir un code : de 3 à 64 caractères de l'alphabet, aucun mot de la
liste de blocage, et un code que personne ne détient. Un code n'est jamais changé une fois que le lien existe, et
jamais réutilisé tant que le lien est là.

## Le décodage

`SqidCodec::decode()` est borné : un identifiant de plus de 255 caractères, avec un caractère hors de l'alphabet, ou
dont le nombre ne tiendrait pas dans un entier PHP, ne décode rien. `isCanonical()` dit si un identifiant est celui que
le codec écrit pour ses nombres : plusieurs identifiants peuvent décoder les mêmes nombres, et un seul est écrit.
