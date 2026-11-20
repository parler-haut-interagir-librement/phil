<!-- phil-dev source-sha256=f1785bf71a100e681255c298a98889c6969d9df0ad4354d87edec784f16e3ccd -->
# Tests

## Les tests du bundle

`tests/Unit` couvre les jeux d'outils et les préréglages, les suggestions et les images téléversées,
`DocumentReferences`, les normaliseurs de chaque jeu d'outils, le transformateur, le type de formulaire et ses options,
les points de terminaison — attribut `access` accordé ou refusé, requêtes de confiance, jetons CSRF acceptés et
refusés, URL, chaque réponse des contrôleurs de téléversement et de recherche — ainsi que la configuration du bundle,
ses services et ses réglages préfixés de Twig, de CSRF et d'AssetMapper. `tests/Functional` démarre un noyau avec
FrameworkBundle, SecurityBundle et un pare-feu paresseux ouvert aux visiteurs anonymes, TwigBundle, `phil/ujo-bundle`
et le bundle, produit le rendu du champ et de son libellé par le thème de formulaire, et appelle les points de
terminaison par les routes, connecté ou non, avec et sans leur jeton et son témoin. Quand le paquet est installé seul, le répartiteur d'événements étant
alors un prérequis de développement, la suite fonctionnelle est sautée.

`tests/js` exécute les modules purs avec l'exécuteur de tests de Node : l'assainisseur comparé à la sortie de
`phil/ujo`, le HTML collé de pages, de Word et de Google Docs, le modèle de document, les outils et les transformations,
les menus, le clavier, les libellés et le jeton CSRF à double soumission.

Depuis la racine du monodépôt, puis depuis le bundle :

```sh
vendor/bin/phpunit packages/block-editor-bundle/tests
node --import ./tests/js/register.mjs --test tests/js     # from packages/block-editor-bundle
```

Chaque ligne de `src/` est tenue par le seuil de mutation du monodépôt : chaque mutant est tué.

## Vérifications dans un navigateur

Le contrôleur et les deux modules qui exigent le DOM — `caret.js` et `view.js` — sont vérifiés à la main dans un
navigateur, sur une page avec Stimulus, la feuille de style et un document qui contient un titre, un paragraphe, une
liste imbriquée, un dépliant, des colonnes et un bloc d'un autre type. Chaque vérification ci-dessous indique quoi faire
et ce qui doit se produire.

| Domaine | Action | Résultat attendu |
|---|---|---|
| Ouverture | Chargez la page | Chaque bloc est dessiné ; le bloc étranger est une carte qui nomme son type ; en lecture seule, aucune marge |
| Scission | À la fin d'un paragraphe, Entrée | Un nouveau paragraphe dessous, le curseur dedans, l'indication affichée |
| Menu `/` | Tapez `/tit`, ↓, Entrée | Le menu liste les titres, le deuxième devient le bloc, le curseur dedans |
| Marques | Dans un nouveau paragraphe, tapez `-`, une espace, `one`, Entrée, Tab, `two` | Une liste à puces, `two` imbriqué sous `one` |
| Quitter une liste | Entrée deux fois dans un élément imbriqué vide | L'élément sort, puis la liste se termine avec un paragraphe après elle |
| Barre d'outils | Double-cliquez sur un mot, cliquez sur B, cliquez sur le bouton de lien, tapez une adresse, Entrée | Le mot en gras et lié, la barre d'outils au-dessus de la sélection, le JSON du champ contenant `<b><a href="…">` |
| Menu de bloc | Cliquez sur une poignée, ↓ jusqu'à *Citation*, Entrée | Le menu s'ouvre sur sa première entrée, les flèches s'y déplacent, le bloc devient une citation avec un champ d'auteur |
| Annulation | ⌘Z | La citation redevient un paragraphe |
| Glisser | Glissez un bloc du dépliant par sa poignée sur la colonne vide | Le bloc passe dans la colonne et est sélectionné ; le dépliant montre son bouton vide |
| Mentions | Tapez ` @a` avec une recherche de mentions qui répond deux personnes | Un menu des deux après *Recherche…*, Entrée insère `@Ada Lovelace` comme mention, la frappe se poursuit après |
| Collage | Collez du HTML avec un titre, une liste et un segment en gras | Un titre, une liste et un paragraphe avec `<b>` après le bloc courant |
| Sélection | Échap, ⌘⇧↓, ⌘D, Retour arrière | Le bloc encadré, descendu, dupliqué, la copie supprimée et le voisin sélectionné, *Bloc supprimé.* annoncé |
| Images | Insérez un bloc image, collez une adresse, Insérer ; ou déposez un fichier avec un téléverseur | L'image affichée avec son champ de légende ; pendant le téléversement, *Envoi…* |
| Enregistrement | Soumettez le formulaire | Le champ contient le document sans blocs vides |
| Écrire à plusieurs | Avec `phil/edit-lock-bundle`, deux rédacteurs sur une page ; l'un tape dans un paragraphe, puis en ajoute un | L'autre voit le paragraphe fermé avec le nom du rédacteur, son texte qui change, le nouveau paragraphe inséré après, le curseur de son propre bloc intact |

## Mesures dans un navigateur

`tests/Browser/measure.mjs`, gardé de l'[essai de ProseMirror](prosemirror-trial.md) archivé, pilote un Chrome sans
interface par le protocole DevTools à travers 28 scénarios — frappe, marques par raccourci sur une sélection et au
curseur, Entrée et Retour arrière entre les blocs, annulation, composition IME, touches mortes, collage — et affiche un
rapport JSON avec le nœud d'accessibilité d'un champ, les octets servis et les temps de dessin. Il exige Node, PHP et
Chrome, ainsi que le module Stimulus de `tests/Browser/package.json` :

```bash
npm ci --ignore-scripts --prefix packages/block-editor-bundle/tests/Browser
node packages/block-editor-bundle/tests/Browser/measure.mjs --chrome=/path/to/chrome
```

## Tester un formulaire qui contient un document

Soumettez le JSON que l'éditeur écrirait, et vérifiez la forme canonique :

```php
$form = static::getContainer()->get('form.factory')->create(ArticleType::class);
$form->submit(['body' => '{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<strong>Hi</strong> <mark>x</mark>"}}]}']);

self::assertTrue($form->isValid());
self::assertSame('{"blocks":[{"id":"a","type":"paragraph","data":{"text":"<b>Hi</b> x"}}]}', $form->getData()->body); // reduced toolset
```

Pour tester un téléverseur ou une recherche, appelez-les directement, ou appelez le point de terminaison avec l'en-tête
de l'éditeur :

```php
$client->request('GET', '/_block-editor/mentions?q=ad', server: ['HTTP_X_PHIL_BLOCK_EDITOR' => '1']);
```
