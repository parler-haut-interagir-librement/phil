<!-- phil-dev source-sha256=98d0d3ac81f657eb4771c71dc7b67f9abdf8d459bc42883000c669f4d1bf5029 -->
# Regroupement

## L'empreinte

`Fingerprinter::fingerprint()` est le SHA-256, en hexadécimal, de chaque exception de la chaîne, dans l'ordre :

1. son type ;
2. son message, passé par le `MessageNormalizer` ;
3. le fichier et la fonction de ses dix premiers cadres de l'application, sans leurs lignes.

Deux événements partagent un groupe quand tout cela est égal. Les lignes sont laissées de côté pour qu'une
modification ailleurs dans un fichier garde le groupe ; les cadres des dépendances sont laissés de côté pour qu'une
mise à niveau garde les groupes de l'application. Un événement sans aucun cadre de l'application est groupé par ses
types et ses messages.

## Le normaliseur de messages

`MessageNormalizer::normalize()` lit les 512 premiers caractères d'un message et remplace, dans cet ordre :

| Quoi | Devient |
|---|---|
| un UUID | `<uuid>` |
| une adresse courriel | `<email>` |
| une adresse avec un schéma, `https://…` | `<url>` |
| un texte entre guillemets doubles ou simples | `<string>` |
| `0x…`, ou douze chiffres hexadécimaux ou plus dont au moins un chiffre décimal | `<hex>` |
| un nombre décimal | `<n>` |

`User 42 not found` et `User 43 not found` se lisent tous deux `User <n> not found`.

## Les états d'un groupe

Un `ErrorGroup` contient l'empreinte, le titre et le coupable de son premier événement, son `GroupStatus`, le nombre
de fois où il a été vu, ses premier et dernier instants, la dernière version vue, et la version sur laquelle il a été
résolu.

| De | Événement ou action | Vers |
|---|---|---|
| aucun | premier événement | `open` : `Occurrence::First` |
| `open`, `regressed` | événement | inchangé : `Repeat` |
| n'importe lequel | `resolve()` | `resolved`, sur la dernière version vue |
| `resolved` | événement de la version sur laquelle il a été résolu | `resolved` : `Repeat` |
| `resolved` | événement d'une autre version, ou l'une des deux versions inconnue | `regressed` : `Regression` |
| n'importe lequel | `ignore()` | `ignored` ; les événements sont comptés, l'état reste |
| n'importe lequel | `reopen()` | `open` |

Un événement de la version sur laquelle un groupe a été résolu vient d'un code qui tourne encore à l'ancienne, un
*worker* pas encore redémarré : il laisse le groupe résolu. `GroupStatus::isUnresolved()` est vrai pour `open` et
`regressed`. `Occurrence::alerts()` est vrai pour `First` et `Regression`.

## Le traqueur

`ErrorTracker` capture dans cet ordre : l'échantillonneur, l'assainisseur, l'empreinte, le limiteur, le groupe lu dans
le magasin et passé à son état suivant, le groupe écrit avec l'événement, et l'alerteur pour un premier événement ou
une régression. `captureThrowable()` et `capture()` retournent une `Capture` (l'identifiant de l'événement,
l'empreinte, l'occurrence et l'état du groupe), ou `null` quand l'échantillonneur ou le limiteur a laissé l'événement
de côté.

Une exception du magasin ou de l'alerteur parvient à l'appelant : le code qui attrape les erreurs décide de ce que fait
une capture ratée, et ne doit pas en lever une à son tour.
