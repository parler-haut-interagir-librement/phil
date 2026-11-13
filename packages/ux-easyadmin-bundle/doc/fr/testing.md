<!-- phil-dev source-sha256=206c24d51529e51b18821e7b1534535ea177472e2293c948bce05f424e47c1f7 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilUxEasyAdminBundle` | La clé et son refus ; l'écouteur et sa priorité, ou aucun service avec `theme: false` ; le répertoire de la feuille de style associé, rien sans FrameworkBundle |
| `ThemeStylesheet` | La feuille de style ajoutée après celle du tableau de bord, une feuille de style déjà listée gardée comme le tableau de bord l'a écrite, une requête sans contexte EasyAdmin laissée telle quelle |
| `ThemeSheet` | Chaque importation d'un fichier de jetons remplacée par sa couche, toute autre ligne gardée, un nom avec un chemin, des majuscules ou rien, une couche sans nom, une requête média ou un commentaire final laissés tels qu'écrits, un fichier manquant et un répertoire refusés ; la feuille de style servie égale à celle que donnent la source et le phil/ux-css installé ; chaque variable du thème déclarée par les feuilles de style d'EasyAdmin installé, pour l'accent, la barre latérale, les tableaux, les formulaires, les alertes et les badges de chaque état, les coins, le focus et les polices ; chaque jeton qu'il lit déclaré par phil/ux-css ; sa couche après celle d'EasyAdmin |
| La recette | Le bundle enregistré dans chaque environnement, sa configuration copiée avec le thème actif |
| L'administration | Un noyau avec EasyAdmin, deux tableaux de bord, SecurityBundle, TwigBundle, TwigComponentBundle et DoctrineBundle sur SQLite : le thème lié après la feuille de style du tableau de bord, une seule fois quand le tableau de bord la liste, absent avec `theme: false` ; la feuille de style trouvée par AssetMapper |

| `tests/Browser` | Une page de l'administration dans un vrai Chrome, servie par le serveur intégré de PHP avec la feuille de style construite d'EasyAdmin : le fond, le texte, l'accent, la barre latérale, une alerte, un badge, une bordure de tableau et les coins calculés à partir des rôles de PHIL UX dans le schéma clair, les rôles sombres dans le schéma sombre, et un style de l'application qui l'emporte sur le thème |

Installée seule, là où FrameworkBundle est une dépendance de développement, la suite fonctionnelle est sautée. La suite
navigateur exige le chromedriver du système et s'exécute à part, à la fin d'un lot.

```sh
vendor/bin/phpunit packages/ux-easyadmin-bundle/tests
php packages/ux-easyadmin-bundle/bin/build.php   # after a change of the source or of phil/ux-css
composer test:browser
```
