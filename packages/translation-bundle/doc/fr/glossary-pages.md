<!-- phil-dev source-sha256=2e34acad3fdc418dd1ed910df268661242ba1f8adaa98fc66c1f57bce1dec74c -->
# Pages de glossaire

Le glossaire d'une paire de langues se modifie à l'écran : un tableau de ses termes, un formulaire qui ajoute ou change
un terme, un bouton qui en supprime un, l'exportation du glossaire en TBX et l'importation d'un fichier TBX. Chaque
modification passe par `Glossaries` du bundle, comme le ferait le code de l'application.

## Routes

| Route | Méthode et chemin | Fait |
|---|---|---|
| `phil_translation_glossary` | `GET /glossary/{source}/{target}` | Les termes et le formulaire |
| `phil_translation_glossary_save` | `POST /glossary/{source}/{target}` | `action=save` ajoute ou change un terme, `action=remove` en supprime un, puis retour avec un `303` |
| `phil_translation_glossary_export` | `GET /glossary/{source}/{target}/tbx` | Le glossaire en pièce jointe TBX |
| `phil_translation_glossary_import` | `POST /glossary/{source}/{target}/tbx` | Un fichier TBX versé dans le glossaire, puis retour avec le nombre de termes |

Ces routes viennent avec celles de l'écran, par `config/routes.php`, après elles : un document nommé `glossary` garde
son écran.

## Qui peut modifier

`TranslationPolicy::canEditGlossary($source, $target)` répond pour l'utilisateur courant. Sans `policy`, les pages
répondent 404 ; pour un utilisateur qu'elle refuse, ou pour deux fois la même langue, 403 ; pour une langue qui n'est
pas une étiquette, 404. Les formulaires venus d'un autre site sont refusés, comme à l'écran.

## Le formulaire

| Champ | Sens |
|---|---|
| `term` | Le terme dans la langue source |
| `forms` | Ses formes fléchies, une par ligne |
| `translations` | Ses traductions, une par ligne ; aucune garde le terme tel quel |
| `translation_forms` | Les formes fléchies des traductions, une par ligne |
| `forbidden` | Les traductions refusées, une par ligne |
| `case_sensitive` | Si la casse compte |
| `note` | Une note pour les traducteurs |

Un terme sans mots, ou de plus de 255 octets, est refusé avec `422` et la raison.

## TBX

`Glossaries::export()` écrit les termes tels qu'ils sont conservés, sans les formes qu'ajoute un fléchisseur, en
TBX-Basic, et `Glossaries::import()` verse dans le glossaire les termes d'un fichier TBX de 2019 ou de 2008 : les
nouveaux termes sont ajoutés, ceux qu'il a remplacés sont comptés. Un fichier de plus de 10 Mio, illisible ou contenant
un terme de plus de 255 octets est refusé avec `422`.

## EasyAdmin

Une interface d'administration EasyAdmin renvoie vers ces pages, qui gardent leurs propres routes, formulaires et mise
en page : le `layout` du bundle, pointé vers un gabarit de l'interface d'administration :

```php
use Phil\TranslationBundle\Infrastructure\EasyAdmin\TranslationAdmin;

public function configureMenuItems(): iterable
{
    yield from TranslationAdmin::menuItems([['en', 'fr'], ['en', 'fr-CA']]);
}
```

`menuItems()` donne un lien par paire de langues, intitulé `Glossary en → fr` sauf si on fournit un libellé contenant
deux `%s`, avec l'icône `fa fa-language` sauf si on en fournit une autre. EasyAdmin est suggéré, non exigé.

## Formes fléchies

Avec un fléchisseur, le glossaire qui trouve et vérifie les termes ajoute les formes qu'il produit :

```yaml
phil_translation:
    inflector: Phil\TranslationMemory\Glossary\RegularPlurals   # the regular plurals of English and French
```
