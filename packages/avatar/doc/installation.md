# Installation

## Requirements

| Package | Version | Why |
|---|---|---|
| PHP | 8.5 or later, with `mbstring` | Initials taken and uppercased by character, labels counted in characters. |

```sh
composer require phil/avatar
```

## The service

`AvatarGenerator` takes the styles of the application, if any, beside the package's three. It keeps no state between
calls: one instance serves every request. In a Symfony application:

```yaml
# config/services.yaml
services:
    Phil\Avatar\AvatarGenerator:
        arguments: [!tagged_iterator app.avatar_style]
```

Without styles of its own, the generator needs no configuration: `new AvatarGenerator()`.
