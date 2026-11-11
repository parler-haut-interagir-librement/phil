<!-- phil-dev source-sha256=cd4eb1145ffc1c16951ebab85eda8d5f1ae168e325aac36c5453318f937bc2d0 -->
# Données

Chaque liste fournie avec la bibliothèque a été écrite pour elle et se trouve sous sa licence MIT.

| Fichier | Ce qu'il contient |
|---|---|
| `data/dictionaries/passwords.php` | 314 mots de passe courants, les plus courants d'abord |
| `data/dictionaries/names.php`, `surnames.php` | 307 prénoms et 135 noms de famille courants en anglais, en français, en espagnol et en allemand |
| `data/dictionaries/english.php`, `french.php`, `spanish.php`, `german.php` | Environ 700 à 1 070 mots courants chacun |
| `data/wordlists/en.php`, `fr.php`, `es.php`, `de.php` | Les mots des phrases de passe : de 3 à 10 lettres ASCII minuscules, chacun une seule fois |
| `translations/phil_passwords.en.php`, `.fr.php` | Les avertissements et les suggestions |
| `translations/validators.en.php`, `.fr.php` | Les messages des contraintes |

Le rang d'une entrée est sa position dans son fichier, à partir de 1 : l'ordre est un jugement de fréquence, pas une
mesure. Les dispositions de clavier sont écrites dans `Strength\KeyboardLayout` sous forme de rangées de touches.

Une application qui a sa propre liste — le nom du site, les mots de son domaine, des mots de passe vus lors de ses
propres incidents — la donne sous forme de `Dictionary`, plutôt que de modifier ces fichiers.
