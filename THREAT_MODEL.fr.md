<!-- phil-dev source-sha256=34d3013f6e45111e1c31df9b5399c522dcbf701ce1415f3496bdf0fa6e431c74 -->
# Modèle de menaces

[English](THREAT_MODEL.md)

## Ce que fait ce projet et où entre l'entrée non fiable

PHIL est un monodépôt de bibliothèques PHP 8.5 et de bundles Symfony (`packages/<name>/`, chacun publié comme
`phil/<name>`). Les bibliothèques sont en PHP pur ; chaque bundle branche sa bibliothèque dans une application Symfony.
Beaucoup existent pour prendre du texte, des fichiers ou des adresses tapés ou téléversés par les visiteurs d'un site
web et les rendre sûrs à stocker, à afficher ou à suivre. Cette entrée n'est pas fiable : supposez qu'un attaquant en
contrôle chaque octet, sa taille et son encodage.

Les paquets qui reçoivent une entrée non fiable, et ce que chacun promet :

| Paquet | Entrée non fiable | Promesse |
|---|---|---|
| `svg-sanitizer` | Images SVG | La sortie est sûre à servir en ligne ou comme fichier : aucun script, gestionnaire d'événements, `foreignObject`, référence externe, entité ni type de document ; le XML est lu sans réseau ; les styles passent par `css-sanitizer` ; des limites de taille, de profondeur, d'éléments et d'attributs refusent une image en entier. |
| `css-sanitizer` | Feuilles de style et attributs `style` | La sortie ne contient que des propriétés, des fonctions, des sélecteurs et des règles @ permis ; `url()` seulement vers des fichiers permis ; `@import`, `expression()`, `javascript:` et les liaisons refusés ; profondeur et longueur d'analyse bornées. |
| `url` | Adresses web | L'analyse suit la norme URL du WHATWG ; une adresse acceptée en http ou en https est normalisée et son hôte, son schéma et sa sorte sont ce que l'appelant voit ; `javascript:` et les autres schémas ne sont jamais classés comme adresses web. |
| `markup` | Markdown, BBCode, MediaWiki, DokuWiki, Creole, UJO, HTML, texte brut | Le HTML compilé à chaque niveau (`basic`, `forum`, `all`) ne contient rien hors de la liste d'autorisation de ce niveau ; aucun script, gestionnaire d'événements ni lien dangereux ; la lecture est linéaire en la longueur de l'entrée. |
| `ujo` | Documents en blocs UJO (Universal JSON Output) en JSON | JSON strict avec limites de taille et de profondeur ; blocs validés ; HTML en ligne purifié ; HTML rendu sûr. |
| `typography` | HTML et texte | Le HTML de sortie a les mêmes éléments et attributs que l'entrée (seuls les nœuds de texte changent) ; aucun balisage n'est introduit ; temps linéaire en l'entrée. |
| `upload-scan` | Fichiers téléversés, réponses du démon antivirus | Un fichier n'est déclaré sain que sur une réponse saine explicite ; les limites de taille et les délais tiennent ; une réponse malformée ou hostile ne donne jamais « sain ». |
| `email-address` | Adresses courriel | Seules les boîtes RFC 5321 / RFC 6531 que l'application permet sont acceptées ; aucune injection d'en-tête par une adresse acceptée ; recherches DNS bornées. |
| `safe-http` | URL à récupérer, réponses distantes | Aucune requête n'atteint un réseau privé, de bouclage, local au lien ou réservé, avant ou après une redirection ou un changement de DNS ; temps, taille et nombre de redirections bornés. |
| `spam-protection` | Envois de formulaires, jetons | Les jetons d'envoi ne peuvent pas être forgés, rejoués après leur fenêtre ni déplacés vers un autre formulaire ; la preuve de travail ne peut pas être sautée. |
| `rate-limit` | Requêtes | Les limites ne peuvent pas être contournées en répartissant les requêtes entre serveurs quand la garde accepte la configuration. |
| `idempotency` | Clés d'idempotence, corps de requête | Une clé est limitée à son appelant — dans `api-contract-bundle`, son locataire, son client d'API et son utilisateur — : un appelant ne peut jamais lire ni rejouer la réponse stockée d'un autre ; une requête ou un message différent sous la même clé est refusé ; un rejeu exécute d'abord les contrôles d'accès du contrôleur. |
| `passwords` | Mots de passe | Estimation de la robustesse en temps borné ; seul un préfixe de hachage de 5 caractères quitte le serveur. |
| `content-bundle` | Corps de pages écrits par les rédacteurs | Un corps est purifié à chaque lecture ; le HTML brut n'est rendu que si son auteur détient le `raw_html_role` configuré, et montré comme texte sinon. |

Les paquets `*-bundle` des bibliothèques ci-dessus (formulaires, contraintes de validation, filtres Twig, contrôleurs)
portent les mêmes promesses à la frontière de l'application.

## Les composants qui comptent le plus / le moins

- Le plus : `svg-sanitizer`, `css-sanitizer`, `url`, `markup`, `ujo`, `safe-http`, `content-bundle`, puis le reste du
  tableau ci-dessus.
- Dans la portée mais moins exposés : les paquets qui ne lisent que la configuration ou des données écrites par les
  développeurs de l'application (enums, money, tax, geo, heures d'ouverture, sitemap…).
- Hors de la portée : les ressources front-end de `ux-css` et de `ux-kit` au-delà du HTML qu'elles émettent ;
  l'outillage de développement (`vendor-bin/`, `bin/`, `phil-dev.php`) ; les jeux de données d'essai et les
  applications de test sous `packages/*/tests/`.

## Les hypothèses

- Les paquets tournent dans une application Symfony. Sa configuration (`config/`, options des bundles, listes
  d'autorisation, rôles) et les données que ses développeurs écrivent sont fiables.
- Les rédacteurs et les administrateurs sont authentifiés par l'application ; les détenteurs de `raw_html_role` sont
  jugés fiables pour publier du HTML brut.
- L'environnement d'exécution PHP, ses extensions et le système d'exploitation sont à jour ; leurs propres défauts sont
  hors de la portée.

## Comment l'exercer

- `composer test` exécute chaque suite ; `vendor/bin/phpunit packages/<name>/tests` exécute un paquet.
- `packages/*/tests/Fuzz/` contient les cibles de fuzzing du fuzzer installé dans `vendor-bin/fuzzer`.
- Chaque paquet documente sa politique et ses limites dans `packages/<name>/doc/`.

## Comment nous évaluons la gravité

- Critique : une sortie d'assainisseur qui exécute un script dans un navigateur (XSS stocké par `svg-sanitizer`,
  `css-sanitizer`, `markup`, `ujo` ou `content-bundle` sans le rôle de HTML brut) ; `safe-http` qui atteint un réseau
  privé ; l'exécution de code à distance.
- Élevée : une URL classée comme web alors qu'elle ne l'est pas, ou avec un autre hôte que celui qu'emploierait le
  navigateur ; un appelant qui lit la réponse idempotente d'un autre ; un jeton anti-pourriel forgé ; un
  téléversement déclaré sain sans réponse saine.
- Moyenne : un déni de service à partir d'une petite entrée (temps ou mémoire super-linéaire au-delà des limites
  documentées) ; une exfiltration de données par CSS seul ou un détournement de l'interface par une sortie assainie.
- Faible : des fuites d'information dans les messages d'erreur ; des défauts qui exigent une configuration fiable pour
  être hostiles.

## Ce qu'il faut laisser de côté

- Un comportement que seuls une configuration fiable ou des rôles fiables peuvent déclencher, comme le disent les
  hypothèses.
- Les protections absentes que la documentation confie à l'application (authentification, CSRF sur ses propres
  formulaires, en-têtes HTTP).

## Contact pour la sécurité

Voyez [`SECURITY.fr.md`](SECURITY.fr.md) à la racine du monodépôt.
