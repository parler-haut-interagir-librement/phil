<!-- phil-dev source-sha256=02894ac9b959698c7dcefa2a9d3348761d45e85cbc1d0e64416195f00aae22e6 -->
# Sécurité

Une action groupée ne contourne jamais une règle d'accès : tout ce que vérifie une modification unique, elle le vérifie
pour chaque élément, à chaque étape.

## Ce qui est vérifié, et quand

| Vérification | Sélection | Aperçu | Confirmation | Chaque lot | Nouvelle tentative |
|---|---|---|---|---|---|
| La cible et l'action sont déclarées | oui | oui | oui | oui | oui |
| La personne peut lancer l'action (`Authorizer::mayRun()`) | oui | oui | oui | oui | oui |
| La sélection respecte les limites ; le dépôt accepte le filtre | oui | oui | oui | le filtre | |
| Le jeton : connu, non expiré, celui de la personne, non utilisé | | oui | oui, puis utilisé | | |
| La modification ne nomme que les champs de l'action | | oui | oui | | |
| L'élément existe et est de la classe de la cible | | échantillon | | oui | |
| Le voter sur l'élément (`Authorizer::mayApply()`) | | échantillon | | oui | |
| La version de l'élément, quand la personne en a vu une | | | | oui, première tentative | |

## La classe vient de la configuration

Une requête nomme une cible et une action, jamais une classe. `Targets::get()` et `Target::action()` refusent tout autre
nom, et chaque élément qu'un dépôt donne est vérifié par rapport à la classe de la cible : l'identifiant d'une autre
table ne peut jamais être modifié par une cible.

## Des voters pour une personne donnée

Une exécution a lieu dans un *worker*, où personne n'est connecté ; l'`Authorizer` est donc interrogé pour la personne
qui a confirmé, par son identifiant. Avec Symfony Security, `UserAuthorizationCheckerInterface::isGrantedForUser()`
interroge les voters pour cet utilisateur ; voyez [Points d'extension](extension-points.md). Un voter qui lit la requête
ou la session ne voit ni l'une ni l'autre dans un *worker*.

## Les jetons

Un jeton de sélection est 32 octets aléatoires, valide pour une personne, une fois, pendant `Limits::lifetimeSeconds`.
Le magasin n'en garde que l'empreinte SHA-256. Chaque échec lance `SelectionUnavailable` avec le même message, de sorte
qu'un jeton ne peut pas être sondé, et un jeton détenu par une autre personne est lu avant d'être pris, de sorte que la
tentative de quelqu'un d'autre ne l'use jamais.

## Les requêtes

Chaque requête qui change quelque chose est un POST avec le jeton CSRF sans état de Symfony et l'origine du navigateur
vérifiée ; un GET montre et ne change jamais. Voyez
[Aperçu et confirmation](confirmation.md#les-requêtes-quenvoie-une-page).

## Les bornes

Chaque entrée est bornée : les identifiants (191 octets, 10 000 par sélection), les noms (64 octets), les filtres
(32 paramètres, 16 Kio), les modifications (64 champs, chaînes de 64 Kio, listes de 1 000), les lots, les échantillons
et les durées de vie. Une sélection ou une exécution conservée que `toArray()` n'a pas pu écrire est refusée à la
relecture.

## Ce que la bibliothèque garde

Un message porte un identifiant d'exécution. Une exécution contient la cible, l'action, l'identifiant de la personne, la
modification, la sélection, ainsi qu'un résultat et une clé de traduction par élément — jamais le message d'une
exception, qui peut citer les données d'un élément. Une exception de la bibliothèque nomme le champ, la cible ou
l'action en cause, jamais les valeurs d'un élément.
