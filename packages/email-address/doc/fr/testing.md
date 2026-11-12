<!-- phil-dev source-sha256=96c378a37f841abaa3563e757e2cdba7902f44fdc41cd07273a91edd9580d06b -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` teste la syntaxe sur chaque raison de refus et à chaque borne de longueur, comptée telle
qu'écrite et en ASCII ; les domaines internationaux, les trois formes de la politique ; les listes de domaines avec
leurs sous-domaines ; les domaines de premier niveau et leur importateur sur la ligne de version, les commentaires, les
doublons, les étiquettes A, une mauvaise ligne et un répertoire où l'on ne peut pas écrire ; l'importateur des domaines
jetables de même, et les lignes de `data/VERSION` que garde chaque importation ; le résolveur sur chaque réponse du DNS, au moyen d'une fonction anonyme ; le suggesteur sur les
fautes, les égalités, les bornes et la distance elle-même, vérifiée contre la table entière sur des paires au hasard ;
le validateur sur l'ordre de ses étapes ; et la contrainte par le `ConstraintValidatorTestCase` de Symfony, sur
chaque problème, message, code et option, et dans un Validator bâti sans conteneur.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/email-address/tests
```

La bibliothèque est tenue par Infection à un score de mutation couverte de 100 % ; les sorties hâtives et le filtre de
la distance, qui ne changent que son coût, sont nommés dans `phil-dev.php` avec leurs raisons.

## Tester du code qui valide des adresses

Donnez au validateur un `Dns\StaticResolver`, pour qu'aucun test n'interroge le DNS :

```php
$validator = new EmailValidator(resolver: new StaticResolver(
    ['example.com' => MailDomain::Mx],
    otherwise: MailDomain::NoRecord,
));

self::assertTrue($validator->validate('ada@example.com')->isValid());
self::assertSame([Problem::NoMailServer], $validator->validate('ada@example.org')->problems);
```

Un test des domaines jetables donne sa propre liste par `DisposableDomains::of()` plutôt que de compter sur la liste
fournie, dont le contenu change à chaque importation ; un test des domaines de premier niveau donne les siens par
`TopLevelDomains::of()`, et un test de la contrainte passe les deux à `DeliverableEmailAddressValidator`.
