# Installation

| Requirement | Version | Why |
|---|---|---|
| PHP | 8.5 or later | The floor the library declares and is tested on |
| `ext-mbstring` | any | Values quoted in messages, cut in characters |

```sh
composer require phil/reservation
```

The library declares no service: an application builds its `Bookable`s from what it stores, or uses
`phil/reservation-bundle` in Symfony.
