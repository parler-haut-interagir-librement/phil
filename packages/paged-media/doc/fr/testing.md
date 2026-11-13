<!-- phil-dev source-sha256=c7ae88bdad6070a9a3d8e2bf3af8d22fc42267f3e0e775bbb187d4394d26a30f -->
# Tests

## Les tests de la bibliothèque

La suite `tests/Unit` écrit ses propres polices et images octet par octet — `TrueTypeBuilder` et `PngBuilder` dans
`tests/Unit/Fixture` — : elle ne dépend donc d'aucun fichier de police ni d'aucune bibliothèque d'images. `PdfReader`
relit ce qu'écrit un test : il vérifie la table de références croisées contre chaque objet et chaque flux contre sa
longueur déclarée, et décompresse les flux.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/paged-media/tests
```

Infection exige de la bibliothèque un score de mutation couverte de 100 %.

Les tests vérifient la structure d'un document PDF/A, pas sa conformité. Après un changement au moteur d'écriture,
produisez un document avec une vraie police TrueType et passez-le dans [veraPDF](https://verapdf.org), le validateur de
référence :

```sh
verapdf --flavour 2u --format text document.pdf
```

Le client Gotenberg est testé contre `MockHttpClient` : `GotenbergServer`, dans `tests/Unit/Fixture`, répond à chaque
requête par la prochaine réponse en file et relit le corps multipart en champs et en fichiers, et
`SelfSignedCertificate` crée dans le test un certificat de signature avec OpenSSL, jamais conservé.

`PageRule` est testée sur le texte qu'elle écrit. Le moteur qui le lit est testé dans son propre paquet.

`tests/Integration` exécute chaque route contre un vrai Gotenberg 9, et est sauté sans lui :

```sh
docker run --rm -d -p 3011:3000 gotenberg/gotenberg:9
PHIL_GOTENBERG_URL=http://localhost:3011 vendor/bin/phpunit packages/paged-media/tests/Integration
```

Le test d'horodatage exige aussi `PHIL_GOTENBERG_TSA`, l'URL d'une autorité RFC 3161 que Gotenberg peut joindre.

## Tester du code qui écrit des PDF

Comme un document est déterministe, le test le plus simple le compare à un fichier de référence :

```php
self::assertStringEqualsFile(__DIR__.'/Fixtures/receipt.pdf', $receiptPdf->render($receipt));
```

Pour faire porter les assertions sur le texte, écrivez plutôt le document avec `compress: false`, ou décompressez ses
flux : chacun est précédé de `/Filter /FlateDecode /Length n >>` et contient `n` octets de données zlib.

## Tester du code qui appelle Gotenberg

Donnez au client un `MockHttpClient` et faites porter les assertions sur ce qu'il envoie et ce qu'il lit :

```php
$client = new MockHttpClient(new MockResponse('%PDF-1.7', ['response_headers' => ['Content-Type' => 'application/pdf']]));
$gotenberg = new Gotenberg($client, 'http://gotenberg:3000');
```

Les vérifications des étapes et de l'ordre d'une liste s'exécutent avant toute requête : le test d'une liste que
Gotenberg refuserait n'a donc besoin d'aucun serveur ; il attend `InvalidPipeline`.
