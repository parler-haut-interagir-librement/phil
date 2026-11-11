<!-- phil-dev source-sha256=b710cda65b8c6a5d39beb5cb040a6aed158670b66382e109886e97c5b0976b98 -->
# Tests

## Les tests du bundle

La suite `tests/Unit` teste le domaine — les bornes d'une période, ses tranches, les limites d'une mesure et d'un
ensemble de séries — ; les périodes prédéfinies dans un fuseau horaire et les périodes qui les précèdent ; les sources,
les tableaux de bord et les calendriers de la configuration ; les images SVG et les barres ; les mesures et leurs
variations dans plusieurs locales ; les requêtes et les tâches écrites puis relues, et chaque forme refusée ; les
rapports bornés et vérifiés ligne par ligne ; l'exécuteur avec ses livraisons et ses échecs ; les formats, cellules de
formule comprises ; les magasins en répertoire et en mémoire par rapport à un même contrat, identifiants mal formés
compris ; la file d'attente, les *handlers* et le calendrier ; les livraisons ; les composants et l'accès à un tableau
de bord réservé ; les téléchargements, la commande et le lien EasyAdmin ; la configuration, les services et les clés
des services étiquetés.

La suite `tests/Functional` démarre un noyau avec TwigComponent, LiveComponent, Messenger sur son transport synchrone
et Mailer sur son transport nul. Elle produit le rendu d'un tableau de bord, d'un tableau de bord en direct, d'une
mesure et d'un graphique, et exécute un rapport planifié jusqu'à son courriel.

À partir de la racine du monodépôt :

```sh
vendor/bin/phpunit packages/reporting-bundle/tests
```

Infection tient le bundle à un score de 100 % des mutants couverts.

## Tester le code qui utilise des rapports

Configurez le magasin `memory` dans l'environnement de test et figez l'horloge :

```yaml
# config/packages/test/phil_reporting.yaml
phil_reporting:
    store: memory
```

```php
$queue->queue(new ReportJob('sales', 'csv', new ReportRequest($periods->period(PeriodPreset::LastMonth))));

self::assertStringStartsWith("Order,Date,Total,Paid\r\n", array_values($exports->contents())[0]);
```

`InMemoryJobStore::ids()` et `InMemoryExportStore::contents()` montrent ce que contiennent les magasins. Une source ou
un rapport se teste seul avec une `Period` construite à la main.
