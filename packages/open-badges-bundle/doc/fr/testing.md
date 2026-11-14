<!-- phil-dev source-sha256=982685ad2d0e507ee91cbd41705a4cba99b6deba8a9979bbc70d3f1a62aa1690 -->
# Tests

## Les tests du bundle

`tests/Unit` teste le câblage sur le `ContainerBuilder` — chaque service et chaque argument, la configuration transmise,
les vérifications qui font échouer la construction, la page écartée sans Twig —, les fabriques de clés, les URL des
listes d'état, la sécurisation sous ses deux formes, ainsi que chaque contrôleur et la vérification des attestations
sur une clé fixe, une horloge figée et des listes en mémoire, les fichiers de contexte, la limite sur les
vérifications de la page avec la passe qui vérifie son limiteur, et les clés d'autres émetteurs lues dans des profils joués par
`MockHttpClient` avec des adresses figées (jetons et documents vérifiés, chaque refus et sa mise en cache, un réseau privé
jamais atteint, les clés qu'un émetteur affirme). `tests/Functional` démarre une petite application avec
FrameworkBundle et TwigBundle et demande le profil, les clés, les listes d'état et la page de vérification, avec des
jetons, des documents et des images avec attestation intégrée; son environnement `limited` ajoute
`phil/rate-limit-bundle` et répond `429` à la troisième vérification d'une minute, et lit d'autres émetteurs, en refusant un émetteur
auquel elle ne se fie pas.

Le noyau de test nomme ses environnements dans `getAllowedEnvs()`, de sorte que chaque construction écrit le même
`config/reference.php` : le conteneur de débogage de chaque environnement reste frais, et un test démarre un conteneur
compilé plutôt que de le construire de nouveau.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/open-badges-bundle/tests
```

Infection exige du bundle un score de mutation couverte de 100 %.

La cible de fuzzing `issuer-profile` (`tests/Fuzz/issuer-profile.php`) nourrit le lecteur des profils d'autres émetteurs
avec des corps que personne n'a écrits : une clé est trouvée, une clé Ed25519, ou refusée avec `InvalidCredential`, et
rien d'autre n'en sort. Ses germes sont dans `tests/Fuzz/corpus/issuer-profile`.

```sh
composer fuzz -- issuer-profile
```

## Tester une application qui émet des badges

Donnez à l'environnement de test un germe fixe — `secret: "\x01..."` de 32 octets, ou une variable d'environnement du
`.env` de test — et une horloge figée : les attestations émises sont alors les mêmes octets à chaque exécution.
Vérifiez-les avec le service `CredentialVerifier`, qui connaît la clé de l'émetteur, et réglez le `default_uri` du
routeur pour que les URL des listes d'état dans les attestations correspondent au site que demandent les tests.
