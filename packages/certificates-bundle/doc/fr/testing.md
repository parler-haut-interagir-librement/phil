<!-- phil-dev source-sha256=38532501422db5f6607b8c2303b628745b03128f3b3eaba2b1f261b6d5d39858 -->
# Tests

## Les tests du bundle

`tests/Unit` teste les objets-valeurs et les entités à leurs bornes, les formations, l'attribution (dont les
attestations sont vérifiées avec la clé de l'émetteur), la révocation, les listes d'état, le dessin du PDF par rapport à
une référence de ses opérateurs et l'intégration de l'attestation par un Gotenberg simulé par `MockHttpClient`, ainsi
que le câblage sur le `ContainerBuilder`. Un lecteur de code QR, dans `tests/Unit/Support/QrReader.php`, reconstruit les
modules à partir des rectangles du PDF et les décode comme le ferait une caméra (format, masque, mots de code,
segments) : les tests lisent donc l'adresse qu'ouvre le code imprimé. La fin des formations et des certificats, les
rappels à chaque seuil, la recertification par des crédits ou des acquis et ses refus, et les sommes de crédits sur des
périodes sont testés en mémoire et sur Doctrine avec SQLite. Les attestations exportées sont vérifiées hors ligne pour la
forme que décrit Open Badges 3.0, par `tests/Unit/Support/Ob3Shape.php`
([Validation](validation.md#ce-qui-est-vérifié-hors-ligne)). Les commandes de console sont testées avec
`CommandTester`, le verrou avec un stockage en mémoire qui dit s'il était tenu pendant que l'indice était pris et le
certificat stocké, chaque tentative d'une attribution dont l'indice était pris comprise. Les attributions sur achèvement sont testées avec
une source d'achèvements qui répond à partir d'une liste et note ce qu'on lui demande.

`tests/Functional` démarre une petite application avec FrameworkBundle, TwigBundle et DoctrineBundle sur SQLite. Il
décerne des certificats, demande les pages et les téléchargements du registre, révoque, lit les dépôts Doctrine, et
exécute les commandes avec la fabrique de verrous et le répartiteur d'événements du framework. Ses verrous sont gardés
en mémoire, un stockage par processus de test, de sorte que des tests exécutés en même temps dans d'autres processus ne
tiennent jamais le verrou qu'un autre atteint; sa source d'achèvements répond à partir d'une liste fixe. Il joue deux
attributions simultanées sans verrou : une autre attribution stocke son certificat à l'indice qu'avait reçu celle-ci,
entre le moment où il a été donné et l'enregistrement, et l'on voit l'attribution reprendre sur un gestionnaire
d'entités réinitialisé avec l'indice suivant, ou abandonner après ses tentatives; et deux attributions sur le même
achèvement, dont la seconde rend le certificat de la première.

`tests/Integration` produit le rendu d'un certificat par un vrai Gotenberg 9, nommé par `PHIL_GOTENBERG_URL`, et vérifie
que l'attestation est le fichier associé du PDF, de relation `Data`, octet pour octet, et que le fichier passe la
validation PDF/A-3b; il est ignoré sans cette variable.

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/certificates-bundle/tests
composer test:services    # the Integration suite against the Gotenberg of phil-dev services
```

Le bundle est tenu à un score de mutation couvert de 100 % par Infection.

## Fuzzing et bancs d'essai

La cible de fuzzing `verification-code` (`tests/Fuzz/verification-code.php`) nourrit les lecteurs des codes de
vérification et des noms de listes d'état avec du texte que personne n'a écrit : un code lu se réécrit à l'identique,
une liste placée contient les indices qu'elle donne, et seule `InvalidValue` est levée. Ses germes sont dans
`tests/Fuzz/corpus/verification-code`.

```sh
composer fuzz -- verification-code
```

`tests/Benchmark/CertificatesBench.php` mesure ce que le registre paie à chaque visite et à chaque publication d'une
liste d'état : un code lu tel que saisi, un indice et une liste placés dans la disposition, et les indices révoqués
d'une liste de dix mille certificats.

```sh
vendor/bin/phil-dev bench packages/certificates-bundle/tests/Benchmark
```

## Tester une application qui décerne des certificats

Donnez à l'environnement de test une clé d'émetteur fixe et une horloge figée (`Symfony\Component\Clock\MockClock`
comme service `clock`) : les dates des certificats sont alors connues. Les codes et les sels des attestations sont
aléatoires; lisez-les dans le `Certificate` retourné. Vérifiez les attestations avec le service `CredentialVerifier` de
`phil/open-badges-bundle`, et simulez Gotenberg avec la `mock_response_factory` du client HTTP, ou réglez
`pdf.bake: false` pour produire le rendu du PDF sans lui.
