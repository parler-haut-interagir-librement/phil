<!-- phil-dev source-sha256=9c0a00b6589daa79a09d78168ee11586acaac3c1a3a26b9e4a22e51efab70097 -->
# Installation

```sh
composer require phil/svg-blocks
```

Le paquet exige PHP 8.5 ou plus récent. Il requiert `phil/ujo`, `phil/qr-code`, `phil/svg-chart`, `phil/latex-mathml`
et `phil/svg-sanitizer`. L'adaptateur Mermaid exige Symfony HttpClient :

```sh
composer require symfony/http-client
```

Le service Mermaid lui-même est une image de conteneur fournie par `phil/svg-blocks-bundle`, dans `docker/mermaid`.
