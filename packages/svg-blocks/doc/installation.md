# Installation

```sh
composer require phil/svg-blocks
```

The package needs PHP 8.5 or later. It requires `phil/ujo`, `phil/qr-code`, `phil/svg-chart`, `phil/latex-mathml` and
`phil/svg-sanitizer`. The Mermaid adapter needs Symfony HttpClient:

```sh
composer require symfony/http-client
```

The Mermaid service itself is a container image shipped by `phil/svg-blocks-bundle`, in `docker/mermaid`.
