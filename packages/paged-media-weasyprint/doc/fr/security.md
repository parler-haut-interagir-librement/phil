<!-- phil-dev source-sha256=42a24a008822c95f45440be6e3555d2e0a53177fcbface62edc5263be18e0585 -->
# Sécurité

Le corps d'un `PrintableHtml` peut contenir du texte venant d'utilisateurs, même une fois assaini, et WeasyPrint suit
ce qu'un document référence : une image, une feuille de style, une pièce jointe. Un
`<link rel="attachment" href="file:///etc/passwd">` mettrait ce fichier dans le PDF. Le moteur confine la commande de
trois côtés.

## La ligne de commande

La commande est une liste d'arguments donnée à `symfony/process`, qui protège chacun par des guillemets, et rien du
document ne l'atteint.

| Argument | Effet |
|---|---|
| `--allowed-protocols data` | WeasyPrint refuse toute adresse qui n'est pas `data:`, y compris `file:` et `http:`, ainsi que celles qu'une image `data:` référence à son tour |
| `--base-url ''` | Une adresse relative n'a rien par rapport à quoi se résoudre |
| `--encoding utf-8` | Le document est lu pour ce qu'il est |
| Les options de `PdfOptions` | Chacune construite à partir d'une valeur vérifiée, comme `--pdf-variant=pdf/x-4` ; voyez [Sortie PDF](pdf-output.md) |
| `- -` | Le document sur l'entrée standard, le PDF sur la sortie standard : aucun fichier temporaire à nettoyer |

Une adresse refusée est journalisée par WeasyPrint et omise de la page ; l'impression continue.

## Aucune adresse distante

Les images distantes sont refusées : le moteur ne charge rien du réseau, et aucune option ne permet de l'activer. La
ligne de commande de WeasyPrint ne peut permettre qu'un protocole entier, et permettre `https` laisserait un document
appeler n'importe quel hôte que le serveur peut atteindre, y compris ceux de son réseau privé. Une application qui a
besoin d'une image distante la télécharge elle-même, selon ses propres règles, et la place dans le répertoire des
ressources ou dans une adresse `data:`.

## Le répertoire des ressources

`LocalAssets` permet à un document de prendre des fichiers sur le disque. Il lit le document avant le lancement de la
commande et y écrit chaque fichier qu'il reconnaît sous forme d'adresse `data:`. La commande n'a jamais le droit de
lire le disque : une référence que cette classe ne reconnaît pas n'est donc pas lue du tout.

Il examine `src` de `img` et de `embed`, `data` de `object`, `href` et `xlink:href` d'une `image` SVG, ainsi que les
`url()` des éléments `style` et des attributs `style`. Une référence est prise quand toutes ces conditions sont
remplies :

| Règle | Refusé |
|---|---|
| Elle est relative | `file:///etc/hosts`, `https://…`, `//host/…`, `/etc/hosts`, `\etc\hosts` |
| Elle n'a ni requête ni fragment | `logo.png?x=1`, `sprite.svg#icon` |
| Son chemin réel est dans le répertoire, une fois les `..`, l'encodage pour cent et les liens symboliques résolus | `../secret.png`, un lien qui pointe à l'extérieur |
| C'est un fichier ordinaire et lisible | Un répertoire, un périphérique |
| Son extension est celle d'une image, d'une police ou d'un profil ICC | `.env`, `.txt`, `.css`, `.html` |

Les extensions prises sont `avif`, `gif`, `icc`, `icm`, `jpeg`, `jpg`, `otf`, `png`, `svg`, `ttf`, `webp`, `woff` et
`woff2`.

Tout fichier de ces types dans le répertoire peut être imprimé par quiconque écrit un document : le répertoire ne
contient donc que des ressources publiques.

Une feuille de style n'est pas téléchargée depuis le répertoire : `PrintableHtml` transporte le texte de ses feuilles
de style. Un fichier SVG pris dans le répertoire ne peut pas référencer un autre fichier, puisque WeasyPrint refuse
l'adresse. Un `@import`, ou une simple chaîne dans `image-set()`, reste non lu pour la même raison.

## Limites

| Limite | Valeur par défaut | Quand elle est dépassée |
|---|---|---|
| La durée d'une impression, `$timeout` de `WeasyPrintEngine` | 120 secondes | La commande est arrêtée, et `WeasyPrintFailed` est lancée |
| La taille d'un fichier, `$maxFileBytes` de `LocalAssets` | 10 Mio | `WeasyPrintFailed`, sans impression |
| Le total d'un document, `$maxTotalBytes`, un fichier utilisé deux fois comptant deux fois | 50 Mio | `WeasyPrintFailed`, sans impression |

La mémoire de la commande est bornée par le système : une limite de conteneur, ou `ulimit` dans un script enveloppe
donné comme `$binary` et qui se termine par `exec weasyprint "$@"`. Un document peut tout de même demander une mise en
page coûteuse, et c'est le délai qui l'arrête. Le nombre de pages n'est pas limité.

L'environnement du processus PHP est transmis à la commande, comme pour tout processus que Symfony lance. La commande
n'exécute aucun script du document et ne peut rien envoyer à l'extérieur, puisqu'elle ne télécharge rien.
