<!-- phil-dev source-sha256=fdac862c95dace15a6a1114a4e6e9fe48cae5396bbc3de6d7c362536f1ad5b2f -->
# Membres

Chaque membre est un paquet indépendant, publié sur Packagist et versionné séparément. Le profil l'exige à sa dernière
version mineure publiée, à partir de la version dont il a besoin pour les correctifs.

## `phil/url-bundle` — les adresses que les gens saisissent

- **Contrainte :** `^0.1.3`, qui démarre sans `doctrine/dbal`. **Apporte :** `phil/url`, `phil/enums`.
- **Rôle :** une adresse donnée dans un formulaire est lue comme le fait un navigateur — par l'analyseur WHATWG de
  PHP 8.5 —, normalisée, et son genre est détecté : un site Web ordinaire, une chaîne vidéo, un compte de réseau
  social.
- **Ajoute :** le champ de formulaire `TypedUrlType`, la contrainte `#[UrlOfType]`, le type de colonne Doctrine, des
  adresses dans Twig.
- **Pourquoi dans ce profil :** une adresse est la première donnée que reçoivent la plupart des formulaires publics, et
  celle que lisent les autres protections.
- **Exige :** `ext-intl` et `ext-mbstring` ; `doctrine/dbal` et DoctrineBundle pour le seul type de colonne.

## `phil/safe-http` — les adresses que l'application va chercher

- **Contrainte :** `^0.2`. **Apporte :** `phil/url`.
- **Rôle :** un client HTTP au-dessus de celui de Symfony, qui refuse les réseaux privés et réservés, revérifie chaque
  redirection, et borne le temps et la taille d'une réponse.
- **Ajoute :** `SafeHttpClient`, `AddressGuard`, `Limits` ; une bibliothèque, sans bundle.
- **Pourquoi dans ce profil :** une fois une adresse acceptée, aller la chercher — pour un aperçu, un fil ou un
  webhook — est le moment où se produit la falsification de requête côté serveur.
- **Exige :** `symfony/http-client`, installé avec lui.

## `phil/rate-limit-bundle` — la fréquence des requêtes d'un client

- **Contrainte :** `^0.1`. **Apporte :** `phil/rate-limit`.
- **Rôle :** des limites déclarées dans la configuration et sur les contrôleurs, les routes et les formulaires ; une
  requête refusée reçoit une réponse 429 avec les en-têtes RateLimit standard.
- **Ajoute :** `#[RateLimit]`, la configuration `phil_rate_limit`, le gabarit de refus ; et, depuis
  `phil/rate-limit`, un conteneur qui ne se construit pas quand un limiteur compte dans un stockage que chaque serveur
  garde pour lui.
- **Pourquoi dans ce profil :** chaque autre protection coûte moins derrière une limite, et un formulaire sans limite
  invite à la force brute.
- **Exige :** `symfony/rate-limiter` et un pool de cache partagé en production.

## `phil/spam-protection-bundle` — le pourriel dans les formulaires

- **Contrainte :** `^0.3`. **Apporte :** `phil/spam-protection`, `phil/bot-detection-bundle`, `phil/rate-limit`.
- **Rôle :** des soumissions de formulaire vérifiées sans captcha, sans tiers et sans pistage : un jeton signé, le
  temps de remplissage, une petite preuve de travail et des règles de contenu ; les détecteurs côté navigateur de
  `phil/bot-detection-bundle` donnent un verdict qui décide de la friction que rencontre un visiteur.
- **Ajoute :** l'option de formulaire `spam_protection`, les contrôleurs Stimulus.
- **Pourquoi dans ce profil :** les formulaires publics sont ce que ce profil protège, et le pourriel en est l'abus le
  plus courant.
- **Exige :** `ext-sodium`, `ext-filter` et, en production, une connexion Doctrine ou une fabrique de verrous que
  tous les serveurs partagent pour les jetons déjà employés.

## `phil/upload-scan-bundle` — les fichiers que les gens téléversent

- **Contrainte :** `^0.1.1`, dont la page de quarantaine vérifie son jeton sans session. **Apporte :** `phil/upload-scan`.
- **Rôle :** chaque fichier téléversé passe par un antivirus pendant la validation du formulaire : sain, infecté ou non
  analysé, avec une politique pour les fichiers non analysés ; les fichiers infectés sont mis en quarantaine, listés
  pour les administrateurs et purgés.
- **Ajoute :** la contrainte `#[CleanFile]`, l'analyseur ClamAV, la quarantaine et sa page, une commande de purge.
- **Pourquoi dans ce profil :** un fichier est la donnée qui atteint les appareils des autres.
- **Exige :** `ext-sodium` et un démon ClamAV.

## Paquets voisins de ce profil

Ces paquets ne sont pas des membres : ils répondent à d'autres besoins, et une application les ajoute quand elle a ces
besoins.

| Paquet | Pourquoi ce n'est pas un membre |
|---|---|
| `phil/browser-security-bundle` | Il protège ce que l'application envoie aux navigateurs — Content Security Policy, HSTS, règles de cadrage et de témoins —, non ce qu'elle reçoit. |
| `phil/ip-anonymizer` | Suggéré par `phil/rate-limit-bundle` pour des clés qui ne gardent aucune adresse complète ; un choix de vie privée de l'application. |
| `phil/svg-sanitizer`, `phil/css-sanitizer` | Ils nettoient le balisage que les gens écrivent, et viennent avec `phil/markup` dans `phil/profile-editorial`. |
