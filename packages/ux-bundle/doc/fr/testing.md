<!-- phil-dev source-sha256=f1456f7285557d8a2b97c6004c7610ff6359b64e3aeb96c7f7a4b58f4057e705 -->
# Tests

| Domaine | Ce qui est couvert |
|---|---|
| `PhilUxBundle` | La clé et son refus, aucun service ; le thème ajouté avant ceux de l'application, la dernière valeur de la clé l'emportant ; le répertoire de la feuille de style associé ; rien sans Twig ou FrameworkBundle |
| Le thème de formulaire | Des formulaires dont le rendu est produit par le moteur de rendu Twig de Symfony : une rangée, les classes de l'application gardées, une erreur et plusieurs, des choix étendus comme un ensemble de champs, des cases à cocher, les erreurs du formulaire, un thème de l'application qui l'emporte, le thème désactivé |
| La feuille de style | Trouvée par AssetMapper sous `phil-ux/phil-ux.css`, depuis le répertoire `dist/` de phil/ux-css |

Une suite fonctionnelle démarre un noyau avec FrameworkBundle, ses formulaires et AssetMapper, TwigBundle et le bundle,
une fois par configuration. Installé seul, là où FrameworkBundle est une dépendance de développement, elle est sautée.

```sh
vendor/bin/phpunit packages/ux-bundle/tests
```
