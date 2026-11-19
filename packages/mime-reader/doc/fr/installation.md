<!-- phil-dev source-sha256=675da2eec5cb5d4ffaf5054943ddd08a6c1b4a833122846192f6d476d71df81f -->
# Installation

```sh
composer require phil/mime-reader
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec `ext-mbstring`, qui convertit les jeux de caractères et
vérifie l'UTF-8, `ext-iconv`, qui convertit les jeux de caractères que mbstring ne connaît pas, et `ext-sodium`, dont
le décodeur base64 s'exécute en temps constant. Elle n'a aucune autre dépendance à l'exécution.

Une application construit elle-même le lecteur, ou l'enregistre comme service : `MimeReader` ne garde aucun état entre
les appels, donc une instance sert chaque requête et chaque worker.

```yaml
# config/services.yaml
services:
    Phil\MimeReader\Limits:
        arguments:
            $maxBytes: 10485760
    Phil\MimeReader\MimeReader: ~
```
