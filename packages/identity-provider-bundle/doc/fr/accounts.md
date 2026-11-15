<!-- phil-dev source-sha256=039532742e9f0fc1db0e5ac83452f6e47a84c1ef32d407d29398f2fadc497e5b -->
# Comptes et mots de passe

## Comptes

Un compte, une ligne de `phil_identity_account`, contient un ULID, une adresse en minuscules et le moment où elle a été
vérifiée, le nom qu'il affiche, l'empreinte Argon2id de son mot de passe — aucune pour un compte qui se connecte
seulement avec des clés d'accès ou un fournisseur fédéré —, un numéro de téléphone et le moment où il a été vérifié,
ses rôles, les échecs de connexion de suite et le verrou auquel ils mènent, et le moment où il a été désactivé. Un
profil plus étoffé relève de l'application.

Symfony Security le voit comme `AccountUser`, identifié par l'ULID du compte — jamais par son adresse, qui peut changer
et ne doit pas voyager dans des liens. `AccountUserProvider` le charge par cet identifiant ou par l'adresse saisie dans
un formulaire de connexion, et réécrit une empreinte que le hacheur de mots de passe demande de renforcer. Deux
utilisateurs sont les mêmes tant que leur identifiant, leurs rôles et leur état le sont : un changement de mot de passe
ne met pas fin à la session où il a été fait ; les autres sessions sont fermées par leurs lignes (voir [Appareils et
sessions](sessions.md)).

`AccountUserChecker` refuse un compte désactivé, par n'importe quelle méthode, avec `account.disabled` et, avec
`require_verified_email`, un compte dont l'adresse n'est pas vérifiée, avec `account.unverified`.

## Inscription

`/register` prend l'adresse, un nom, et le mot de passe quand le module `password` est activé. Une adresse qui a déjà
un compte ne crée rien et la page répond la même chose — « vérifiez votre courriel » —, de sorte que le formulaire ne
dit à personne qui a un compte ; l'événement `identity.account.registration_for_known_email` permet à l'application
d'avertir le titulaire. Un nouveau compte reçoit un lien qui vérifie son adresse.

## Adresses vérifiées

Le lien de `/verify-email` porte l'identifiant du compte, une expiration et une signature calculée par le
`SignatureHasher` de Symfony Security, celui qu'utilisent ses liens de connexion, sur l'adresse et la date de sa
vérification, sous une clé dérivée de `secret` pour cet usage seulement. Il cesse de fonctionner une fois suivi, une
fois l'adresse changée, ou après `links.verify_lifetime` secondes, sans rien stocker. Une adresse changée n'est pas
vérifiée tant que son propre lien n'a pas été suivi.

## Mots de passe

Un nouveau mot de passe doit compter de 8 à 4096 caractères — la borne empêche un calcul d'empreinte de devenir un déni
de service —, atteindre `password.min_score` sur l'estimateur de force de `phil/passwords`, l'adresse et le nom du
compte comptant parmi les premières hypothèses de l'attaquant, et, avec `password.breach_check`, être absent de Pwned
Passwords, interrogé par k-anonymat. Une source de fuites qui ne répond pas ne fait pas refuser le mot de passe. Les
raisons reviennent sous forme de clés de traduction : `password.too_short`, `password.too_long`, `password.too_weak`,
`password.breached`.

Les mots de passe sont hachés par le hacheur de mots de passe de Symfony Security : le bundle préconfigure `sodium` —
Argon2id — pour `AccountUser`, et l'application peut l'ajuster dans `security.password_hashers`.

## Le mot de passe oublié

`/forgot-password` envoie un lien à l'adresse quand elle a un compte, et répond la même chose quand elle n'en a pas. Le
lien de `/reset-password` est signé sur l'empreinte du mot de passe et la date de son dernier changement : il cesse de
fonctionner dès que le mot de passe change, ou après `links.reset_lifetime` secondes. Le suivre et choisir un mot de
passe vérifie aussi l'adresse, ce que le lien a prouvé.

## Le verrou

`PasswordSignInListener` surveille les connexions par mot de passe de chaque authentificateur du pare-feu —
`form_login`, `json_login`, `http_basic`. Avant que le mot de passe soit vérifié, un compte verrouillé est refusé avec
`account.locked`, de sorte que les tentatives de devinette se poursuivent contre rien ; un mauvais mot de passe compte
vers le verrou ; une réussite remet le compte à zéro. Après `lockout.threshold` échecs de suite, chaque échec de plus
verrouille le compte deux fois plus longtemps que le précédent, de `lockout.base` secondes jusqu'à `lockout.max`. Le
verrou refuse seulement le mot de passe : une clé d'accès, qu'un attaquant qui devine ne peut pas rejouer, connecte
quand même. Le `login_throttling` du pare-feu limite les tentatives par adresse réseau devant lui.

## La page du compte

`/account` montre le nom, l'adresse et si elle est vérifiée, les formulaires qui les changent ainsi que le mot de
passe, et des liens vers les pages des modules activés. Changer l'adresse ou le mot de passe exige une preuve récente de
la méthode la plus forte du compte : voir [Réauthentification](step-up.md).
