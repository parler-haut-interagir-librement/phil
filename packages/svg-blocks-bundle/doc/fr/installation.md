<!-- phil-dev source-sha256=c194a0169f440a9462c1c4ddf65c02a10807f88d1c84a48749dbf4512756c9fc -->
# Installation

```sh
composer require phil/svg-blocks-bundle
```

Sans Flex, activez le bundle après `phil/ujo-bundle` :

```php
// config/bundles.php
Phil\UjoBundle\PhilUjoBundle::class => ['all' => true],
Phil\SvgBlocksBundle\PhilSvgBlocksBundle::class => ['all' => true],
```

## La route de l'aperçu

```yaml
# config/routes/phil_svg_blocks.yaml
phil_svg_blocks:
    resource: '@PhilSvgBlocksBundle/config/routes.php'
    prefix: /_svg-blocks
```

Placez le préfixe derrière le pare-feu des rédacteurs. L'aperçu a besoin de la protection CSRF de FrameworkBundle,
active par défaut. Le bundle ajoute son jeton aux jetons sans état : aucune session n'est donc nécessaire.

## Le contrôleur

Avec AssetMapper et le bundle Stimulus, le contrôleur est associé à `@phil/svg-blocks-bundle` dans la configuration
d'AssetMapperBundle, et activé dans `assets/controllers.json`, comme tout paquet UX. Il est chargé quand son premier
élément apparaît dans une page :

```json
"@phil/svg-blocks-bundle": {"preview": {"enabled": true, "fetch": "lazy"}}
```

## Le service Mermaid

```sh
docker build -t phil/mermaid-service vendor/phil/svg-blocks-bundle/docker/mermaid
```

Lancez-le à côté de l'application, sur un réseau qui n'atteint rien d'autre, avec les limites de
`docker/mermaid/compose.yaml`, et réglez `phil_svg_blocks.mermaid.url`. [Diagrammes](diagrams.md) contient les
conditions que fixe son examen de sécurité.
