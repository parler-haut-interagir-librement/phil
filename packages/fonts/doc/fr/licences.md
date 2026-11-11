<!-- phil-dev source-sha256=4cb671d64bbd70bba66247067cac268f7c22688a3303d19b84105ee03d589322 -->
# Licences

## Ce que dit une face

`$font->licence()` donne une `Licence` avec la description (nom 13) et l'URL (nom 14) qu'indique la police ; l'une ou
l'autre peut être null. `spdx()` reconnaît les licences sous lesquelles la plupart des polices sont publiées :

| Identifiant SPDX | Reconnu à partir de |
|---|---|
| `OFL-1.1` | `openfontlicense.org`, `scripts.sil.org/OFL`, ou « Open Font License » |
| `Apache-2.0` | `apache.org/licenses/LICENSE-2.0`, ou « Apache License, Version 2.0 » |
| `UFL-1.0` | `ubuntu.com/legal/terms-and-policies/font-licence`, ou « Ubuntu Font Licence » |
| `MIT` | `opensource.org/licenses/MIT`, ou « MIT License » |

La comparaison ignore la casse et se fait séparément sur l'URL et sur la description. C'est un indice pour un registre
ou un écran d'administration, pas une lecture juridique : la licence qui engage est celle sous laquelle la police a été
obtenue, et une police commerciale ne l'indique souvent que dans un fichier distinct.

## Fiches de licence

Une `LicenceRecord` est ce qu'une application conserve au sujet d'un fichier de police : son chemin, son SHA-256, son
`Format`, et une `FaceLicence` par face. Celle-ci contient la famille, la sous-famille, le nom PostScript, la version,
le copyright, la marque de commerce, le fabricant, le dessinateur, l'URL du fournisseur, la description et l'URL de la
licence, l'identifiant SPDX, l'usage d'incorporation, et indique si les sous-ensembles sont permis et si la police est
limitée aux bitmaps. `LicenceRecord::of(string $file, FontFile $font)` en construit une ; `licence()` donne celle de la
première face, que les faces d'une collection partagent habituellement.

## Le registre

Un `LicenceRegister` contient une fiche par fichier, triées par chemin.

| Méthode | Effet |
|---|---|
| `LicenceRegister::scan(string $directory, ?Closure $brotli = null)` | Consigne chaque fichier `.ttf`, `.otf`, `.ttc`, `.otc`, `.woff` et `.woff2` sous un répertoire, sans tenir compte de la casse de l'extension, par son chemin relatif au répertoire avec `/` entre les dossiers ; un fichier qui n'est pas une police lance `InvalidFont` |
| `toJson()`, `LicenceRegister::fromJson(string $json)` | Le registre sous forme de JSON mis en forme, et l'inverse ; un JSON qui n'est pas un registre lance `InvalidLicenceRegister` |
| `with(LicenceRecord $record)` | Un registre avec une fiche ajoutée, ou mise à la place de la fiche du même fichier |
| `get(string $file)`, `records()` | Une fiche par son chemin, ou toutes les fiches |
| `changedFiles(LicenceRegister $other)` | Les fichiers présents dans un seul des deux registres, ou avec un autre SHA-256 : les polices dont la licence doit être vérifiée de nouveau |

```json
{
    "fonts": [
        {
            "file": "example/ExampleSans-Regular.ttf",
            "sha256": "9f2c…",
            "format": "truetype",
            "faces": [
                {
                    "family": "Example Sans",
                    "subfamily": "Regular",
                    "postScriptName": "ExampleSans-Regular",
                    "version": "Version 2.000",
                    "copyright": "Copyright 2026 The Example Sans Project Authors",
                    "trademark": null,
                    "manufacturer": null,
                    "designer": null,
                    "vendorUrl": null,
                    "licence": "This Font Software is licensed under the SIL Open Font License, Version 1.1.",
                    "licenceUrl": "https://openfontlicense.org",
                    "spdx": "OFL-1.1",
                    "embedding": "installable",
                    "subsetting": true,
                    "bitmapOnly": false
                }
            ]
        }
    ]
}
```

Gardez le registre sous gestion de versions à côté des polices. En intégration continue, balayez le répertoire et
comparez-le au registre conservé : un fichier ajouté ou modifié fait échouer la construction jusqu'à ce que quelqu'un
ait lu sa licence et fait un commit du nouveau registre.

```php
$kept = LicenceRegister::fromJson(file_get_contents('assets/fonts/licences.json'));
$changed = $kept->changedFiles(LicenceRegister::scan('assets/fonts'));

if ([] !== $changed) {
    fwrite(STDERR, "Fonts whose licence was not recorded:\n  ".implode("\n  ", $changed)."\n");
    exit(1);
}
```

Les fiches contiennent ce que disent les polices. Ajoutez ce qu'elles ne disent pas (où une police a été achetée, le
numéro de la licence, les domaines qu'elle couvre) dans un fichier propre à l'application, avec les mêmes chemins comme
clés.
