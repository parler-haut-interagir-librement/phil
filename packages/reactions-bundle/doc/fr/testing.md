<!-- phil-dev source-sha256=20dafbf02b37554eb24b9613925a19255c454d8863b508da01fbca768a2c4f82 -->
# Tests

```sh
vendor/bin/phpunit packages/reactions-bundle/tests
cd packages/reactions-bundle && node --import ./tests/js/register.mjs --test tests/js
```

| Suite | Ce qu'elle couvre |
|---|---|
| `tests/Unit` | Le domaine, le catalogue, réagir et retirer, ce que voit celui qui regarde, les stockages selon un même contrat sur SQLite en mémoire, la garde, le contrôleur, le composant, les adaptateurs et la configuration |
| `tests/Functional` | Un noyau avec les recettes de chaque kit copiées là où le ferait le Toolkit, dans un sous-répertoire ou non, et le stockage Doctrine sur un fichier SQLite à lui : la page, les simples formulaires, la signature, la liste des noms et le Live Component ; avec AssetMapperBundle et StimulusBundle, la table des contrôleurs que compile StimulusBundle, les deux contrôleurs chargés paresseusement |
| `tests/js` | Les contrôleurs Stimulus sous l'exécuteur de tests de Node |
| `tests/Benchmark` | Une page de 20 contenus lus en un lot, puis dessinés par le composant entier dans un noyau réinitialisé entre deux requêtes |
| `tests/Worker` | La page que mesure `composer bench:worker phil/reactions-bundle`, servie par FrankenPHP en mode *worker* ; avec `--ext`, sur une image dotée de `ext-intl` |
| `tests/Browser` | Les vérifications ci-dessous dans un Chrome sans fenêtre, avec le Live Component et les contrôleurs Stimulus en marche |

Dans les tests propres à une application, l'option `store: memory` garde les réactions en mémoire pour une requête : il est
vidé entre deux. Un test qui réagit, puis relit la page, emploie le stockage Doctrine sur SQLite, comme le fait `tests/Functional/App/TestKernel.php`.

## Dans un navigateur

La suite du navigateur s'exécute à part des autres, avec le chromedriver du système et une version de Stimulus nommée par
`PHIL_STIMULUS_JS` ; elle est sautée sans lui :

```sh
PHIL_STIMULUS_JS=node_modules/@hotwired/stimulus/dist/stimulus.js \
    vendor/bin/phpunit --configuration=phpunit.browser.dist.xml packages/reactions-bundle/tests/Browser
```

Elle sert le noyau de la suite fonctionnelle par `tests/Browser/public/index.php`, dont la page charge les contrôleurs
par une table d'importation, ses réactions gardées entre deux requêtes dans le fichier SQLite du noyau, vidé au départ de la suite, et pilote chromedriver sur son propre port, 9581, à côté d'une autre suite.

| Vérification | Attendu |
|---|---|
| Appuyer sur une réaction au clavier | Le focus reste sur le bouton, le compteur change, un lecteur d'écran dit que la réaction est enregistrée |
| Un contenu dessiné deux fois | Appuyer sur l'un met l'autre à jour |
| Le sélecteur | Il s'ouvre sur la recherche ; les flèches, Début, Fin et Échap font ce que dit [le composant](component.md) |
| JavaScript désactivé | Chaque bouton réagit par le formulaire et revient à la même page |
| Plafond de `per_member` atteint | Une phrase après la barre ; les autres réactions ne peuvent pas être pressées avant qu'une soit retirée |

Ce que dit à voix haute un lecteur d'écran — la région active, le libellé et le compteur d'un bouton, la divulgation des
noms — est entendu par une personne, avec VoiceOver et NVDA au moins : la suite vérifie le balisage qu'ils lisent, non
leur parole.
