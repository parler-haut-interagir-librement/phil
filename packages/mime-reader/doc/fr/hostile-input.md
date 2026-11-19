<!-- phil-dev source-sha256=394a0678fb1582ecb501c1d3c4812c4ebe3139263d9214cbff89339031ef542a -->
# Entrée hostile

Une boîte de courriel ouverte au monde reçoit des messages écrits pour casser leur lecteur. Le lecteur respecte trois
règles : il ne lance jamais d'exception sur un message, il ne fait jamais plus de travail que ses limites ne le
permettent, et il n'agit jamais sur ce qu'un message contient.

## Les limites

| `Limits` | Valeur par défaut | Ce qu'elle borne |
|---|---|---|
| `maxBytes` | 25 MiB | Les octets du message lus ; le reste n'est pas lu |
| `maxDepth` | 12 | L'imbrication des multiparties et des messages contenus, le message lui-même compris |
| `maxParts` | 256 | Les parties lues en tout, le message lui-même compris |
| `maxHeaders` | 500 | Les champs d'en-tête lus dans chaque partie |
| `maxParameters` | 32 | Les paramètres lus dans un champ d'en-tête |

Chacune vaut au moins 1 ; sinon `InvalidLimits` le dit. Une partie coupée par une limite est marquée `incomplete` : le
message coupé à `maxBytes`, une multipartie dont les parties ont dépassé `maxParts`, un conteneur à `maxDepth` dont les
parties restent non lues et sont gardées comme son corps. Les parties lues avant la limite sont conservées.

## Un travail borné

Chaque recherche d'une frontière ou d'une ligne vide reste dans sa partie, par fenêtres qui passent de 4 KiB à 1 MiB,
de sorte qu'une frontière jamais fermée n'envoie pas chaque partie chercher jusqu'à la fin du message. Les parties d'un
message partagent ses octets au lieu de les copier, et les corps ne sont décodés que sur demande, par morceaux de
64 KiB. Des milliers de parties, une imbrication bien plus profonde que la limite et un mégaoctet de texte coûtent
chacun des millisecondes, comme le mesurent les bancs d'essai.

## Ce qui est refusé

- Un type de média qui n'est pas un type et un sous-type de caractères de jeton prend la valeur par défaut de sa place.
- Une frontière de plus de 200 caractères ou contenant un caractère de contrôle laisse la multipartie non découpée.
- Une ligne d'en-tête sans deux-points, ou dont le nom n'est pas imprimable, est ignorée.
- Un numéro de section de paramètre de plus de trois chiffres est ignoré.
- Un jeu de caractères inconnu à la fois de mbstring et d'iconv, un nom contenant un `/` qu'iconv lirait comme ses
  options, ou un nom qui désigne un encodage de transfert ou des entités plutôt qu'un jeu de caractères, est lu
  comme de l'UTF-8 dont les séquences invalides sont remplacées.

## Ce qui n'est jamais fait

- Rien dans un message n'est exécuté, inclus, désérialisé ni récupéré : aucun HTML n'est rendu, aucune URL n'est
  suivie, aucun `Content-Location` n'est résolu.
- Un nom de fichier n'est jamais un chemin : les répertoires, les caractères de contrôle et les points initiaux sont
  retirés.
- Tout texte que donne le lecteur — champs décodés, noms d'affichage, paramètres, `text()` — est de l'UTF-8 valide.
- Les adresses ne sont pas validées, donc pas considérées sûres : une `Mailbox` est ce que l'expéditeur a écrit.
