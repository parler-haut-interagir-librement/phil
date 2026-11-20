<!-- phil-dev source-sha256=f15c9c0fad547753fa33fe1a209c412fb9482af9edb316d63f5972c15783cfb6 -->
# Correspondance avec ASVS 5.0

Les exigences du chapitre V6 d'[OWASP ASVS 5.0.0](https://github.com/OWASP/ASVS/tree/v5.0.0/5.0/en) que la
bibliothèque satisfait, avec le test qui montre chacune, et celles qu'elle laisse à l'application. Le guide du
monodépôt,
[ASVS 5.0 : ce que l'application garde](https://github.com/parler-haut-interagir-librement/phil/blob/main/ASVS.md),
rassemble ce que chaque paquet laisse.

## Satisfaites par la bibliothèque

| Exigence | Niveau | Comment | Démontrée par |
|---|---|---|---|
| 6.2.5 Mots de passe de toute composition, aucune règle de caractères | 1 | La robustesse est estimée d'après les essais dont un attaquant a besoin, jamais d'après des classes de caractères | `StrengthEstimatorTest` |
| 6.2.8 Le mot de passe utilisé exactement tel que reçu | 1 | L'estimateur et la vérification de fuite lisent les octets donnés : ni rognage, ni troncature, ni mise en minuscules, ni normalisation Unicode avant l'envoi du préfixe de l'empreinte | `PwnedPasswordsSourceTest::testThePasswordIsHashedExactlyAsTyped`, `NotCompromisedValidatorTest::testThePasswordIsCheckedAsTyped` |
| 6.2.9 Mots de passe d'au moins 64 caractères | 2 | Toute longueur est acceptée ; l'estimateur analyse les 128 premiers caractères, si bien qu'un long mot de passe coûte un temps borné | `MinimumStrengthValidatorTest::testAPasswordOf200CharactersIsAccepted` |
| 6.2.11 Mots propres au contexte refusés | 2 | `PasswordUserInputs` et les entrées d'utilisateur de `estimate()` — nom, adresse courriel, nom du site — sont cherchées en premier | `StrengthEstimatorTest`, `MinimumStrengthValidatorTest` |
| 6.2.12 Mots de passe compromis refusés | 2 | `NotCompromised` et `BreachChecker`, par k-anonymat : seuls cinq caractères du SHA-1 quittent le serveur | `NotCompromisedValidatorTest`, `PwnedPasswordsSourceTest` |
| 6.2.4 Les 3 000 mots de passe les plus courants refusés | 1 | Satisfaite avec `NotCompromised`, dont la source les contient ; le dictionnaire hors ligne de l'estimateur en contient 314 | `NotCompromisedValidatorTest` |

## Laissées à l'application

| Exigence | Niveau | Pourquoi elle revient à l'application |
|---|---|---|
| 6.1.2 La liste documentée des mots propres au contexte | 2 | L'application connaît son nom, son domaine et ses gens ; elle les passe comme entrées d'utilisateur |
| 6.2.1 Au moins 8 caractères, 15 recommandés | 1 | Une contrainte `Length` sur le champ du formulaire, à côté de `MinimumStrength` |
| 6.2.2, 6.2.3 Changer le mot de passe, avec l'actuel | 1 | Les pages de compte de l'application |
| 6.2.6, 6.2.7 `type=password`, collage et gestionnaires de mots de passe permis | 1 | Le balisage et les scripts du formulaire |
| 6.2.10 Aucune rotation périodique | 2 | La politique de compte de l'application |
| 6.2.12 lorsque la source ne peut répondre | 2 | `skipOnError` décide : refuser le mot de passe, ou l'accepter et compter l'échec ([fuites](breaches.md)) |
