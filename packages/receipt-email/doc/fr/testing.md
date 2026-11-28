<!-- phil-dev source-sha256=462d717b22958ca10a227d8509cd7e12c1b284a5b3b6ca169b8550e3f9a2788f -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Elle couvre les
objets-valeurs et leurs refus, les montants et les taxes lus dans les reçus de `phil/commerce-bundle` — notes de crédit
et taxes incluses dans les prix aussi —, les variables de chaque message, les gabarits par défaut rendus par
`phil/email-templates-bundle` en anglais et en français et vérifiés comme l'enregistrement d'un site les vérifie, les
vérifications transactionnelles, les PDF, et les messages de Messenger acheminés vers un transport asynchrone,
sérialisés, puis traités par un *worker* qui envoie par un transport de Mailer. Dans le monodépôt, Infection maintient
le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/transactional.php` donne à `TransactionalCheck::check()` du HTML que personne n'a écrit, à partir des
germes de `tests/Fuzz/corpus/transactional-html` : il retourne le reçu ou lève `NotTransactional`, et rien d'autre
n'échappe.

```sh
composer fuzz transactional-html
```
