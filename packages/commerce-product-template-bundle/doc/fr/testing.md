<!-- phil-dev source-sha256=b34c855cfb4daf395039323daf70af8d9fd87263b8ada4b8d8528abc53dfb170 -->
# Tests

## Les tests du module

`tests/Unit` s'exécute sans noyau, sur des dépôts en mémoire : une génération crée les douze produits d'un motif de
t-shirt en deux langues, s'exécute de nouveau sans changement, met à jour un prix et une catégorie de taxe, retire une
langue abandonnée puis la rétablit, refuse une UGS d'un autre groupe, un produit hors de tout groupe, un changement de
sorte ou de devise, et laisse le catalogue intact quand elle refuse. Les options d'un produit sont vérifiées avec leurs
conditions et avec une option de motif qui remplace celle du gabarit ; les maquettes sont composées à partir d'images
PNG dessinées par GD et comparées pixel par pixel, nommées par leur contenu, composées une fois puis de nouveau
seulement quand une image change. Les options sont écrites en JSON et relues identiques ; chaque tableau qu'aucun code
n'a écrit est refusé. La production compose les fichiers d'impression d'un petit t-shirt à 72 ppp — des objets fixes,
deux dés d'objets, une pierre tombale, une épitaphe dans une police d'essai, une encre lumineuse —, les vérifie pixel
par pixel, regroupe les unités imprimées de la même façon, n'écrit rien de plus quand elle s'exécute de nouveau, et
refuse une commande non passée ou sans tirage, un tirage qui ne se lit pas, un texte qui ne tient pas, et une image ou
une police manquante ; le *handler* de Messenger réessaie ce qui peut réussir plus tard, et rien d'autre. Les éléments
d'impression sont relus identiques depuis le JSON, et les dés se lisent et s'écrivent à raison d'un caractère par dé.

`tests/Functional` démarre un noyau avec le noyau du commerce, le catalogue et le module sur une base SQLite en
mémoire : un gabarit et un motif sont enregistrés, générés, vidés par `flush`, générés de nouveau sans changement,
leurs maquettes écrites dans un répertoire temporaire, leur page lue, et une ligne de commande configurée avec une
option du gabarit facturée par le noyau ; une commande payée d'un t-shirt à coffre à butin est produite par le message
que son achat envoie, ses fichiers écrits dans un répertoire privé.

`tests/js` exécute le contrôleur Stimulus avec l'exécuteur de tests de Node, sa dépendance de pair résolue vers un
*stub* :

```sh
node --import ./tests/js/register.mjs --test tests/js
```

Depuis la racine du monodépôt :

```sh
vendor/bin/phpunit packages/commerce-product-template-bundle/tests
```

Infection maintient le module à 100 % de score de mutation couverte.

## Tester une application qui génère

Générez dans la base de test de l'application, faites le `flush`, et vérifiez le rapport plutôt que les rangées :

```php
$report = $generation->generate('NAQ001', 'unisex');
$entityManager->flush();

self::assertTrue($generation->generate('NAQ001', 'unisex')->changedNothing());
```

Pour les maquettes, faites pointer `images_directory` vers un répertoire temporaire et écrivez-y de petites images PNG.

Pour la production, faites pointer `production.directory` vers un répertoire temporaire, configurez une petite police
sous `production.fonts`, donnez aux zones d'un gabarit d'essai une faible résolution pour que les fichiers se composent
vite, et vérifiez le `ProductionOrder` rendu plutôt que les octets.
