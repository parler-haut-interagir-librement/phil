<!-- phil-dev source-sha256=fbae79982b557f8244a15d86d5e95dbeae51ca421bdbfd97900416057a1bd861 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Le Google local

`tests/Fixture/FakeGoogle` tient lieu de Google, hors ligne :

- son document de découverte et le document de configuration de la Cross-Account Protection sont des jeux de données
  d'essai de la forme que Google publie, dans `tests/Fixture` ;
- son jeu de clés contient une clé RSA créée pour l'exécution, et `FakeGoogle::sign()` signe avec elle les jetons
  d'identité et les jetons d'événement de sécurité, ou avec une autre clé pour tester une signature falsifiée ;
- son point de terminaison des jetons répond avec le jeton d'identité qu'un test a choisi, et chaque requête est
  gardée, avec ses en-têtes et son corps ;
- les noms d'hôte sont fixés à des adresses publiques de la plage de documentation, de sorte que phil/safe-http ne
  résout rien et applique tout de même sa garde ; le transport HTTP est le `MockHttpClient` de Symfony.

`SignInHarness` construit `GoogleSignIn` comme le font les services du bundle — découverte, client, vérificateur de
signature et vérifications de revendications de Symfony sur le Google local —, avec une session et une horloge à une
heure fixe. `RiscHarness` construit le récepteur sur les magasins en mémoire de phil/identity-provider-bundle : un
compte lié au sujet Google, avec deux sessions ouvertes.

## La cible de fuzzing

`tests/Fuzz/google-claims.php` lit du JSON que personne n'a écrit comme les revendications d'un jeton
d'événement de sécurité et comme celles d'un jeton d'identité de Google. Il n'accepte qu'un jeton dans ses bornes ou
`InvalidSecurityEventToken`, et une identité dont le sujet est imprimable et dont l'adresse n'est de confiance que sur
l'autorité de Google, ou `Refused` :

```sh
composer fuzz -- google-claims
```

Ses germes sont dans `tests/Fuzz/corpus/google-claims`.
