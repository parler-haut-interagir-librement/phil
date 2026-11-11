<!-- phil-dev source-sha256=0760ad32f2f64109af5fcdda6705650756b34e3a734bea093a8cb9b81ddbc5d8 -->
# Configuration

```yaml
# config/packages/phil_qr_code.yaml
phil_qr_code:
    error_correction: M       # L, M, Q or H
    quiet_zone: 4             # 0 to 16 modules
    dark: '#000000'           # #rrggbb
    light: '#ffffff'          # #rrggbb
    size: 0                   # the width and height of an SVG in pixels, 0 to 4096; 0 lets its container size it
    png_scale: 8              # the pixels on a side of a module of a PNG, 1 to 32
    eci: true                 # announce bytes beyond ASCII as UTF-8
    kanji: true               # write the double-byte characters of Shift JIS in the kanji mode
    max_bytes: 2048           # the longest payload, 1 to 7089 bytes
    logos:
        brand: { path: '%kernel.project_dir%/assets/logo.svg', zone: 0.2 }
    route:
        enabled: false        # serve codes at signed addresses; import config/routes.php too
        cache_max_age: 31536000
```

Chaque clé est vérifiée à la construction du conteneur : un niveau, une couleur, une taille ou une zone hors de sa
plage arrête la construction en donnant la raison.

## Les valeurs par défaut

Les valeurs par défaut s'appliquent à chaque code que dessine le bundle — dans Twig, dans le composant, à la route,
dans les blocs UJO et dans les documents PDF —, et chaque appel peut remplacer `level`, `quiet_zone`, `dark`, `light`,
`size`, `scale`, `logo` et `title` (voir [Twig](twig.md)). Gardez la couleur foncée foncée et la claire claire : les
lecteurs ont besoin du contraste.

`max_bytes` borne la charge utile d'un code, pour qu'un gabarit ou un bloc ne demande jamais par erreur un code de
version 40 ; une charge utile au-delà est refusée avec `InvalidContent`.

## Les logos

Un logo a un nom, un fichier — PNG, JPEG, WebP ou SVG, d'au plus 1 Mio, reconnu par ses octets — et `zone`, la part
du côté du code qu'il occupe, de 0,05 à 0,3 (0,2 par défaut). Un code dessiné avec un logo est relevé au niveau H et
rendu assez grand pour que chaque bloc corrige la zone : voir les [logos](../../../qr-code/doc/fr/logos.md) de
phil/qr-code.

Les gabarits nomment un logo ; ils ne donnent jamais de chemin, de sorte qu'aucun gabarit ne lit un fichier que la
configuration n'a pas listé. Un fichier est lu quand son logo est dessiné pour la première fois, puis gardé pendant
toute la vie du processus. Un PNG et un PDF placent les logos PNG, JPEG et WebP — WebP dans le PNG seulement — ; un
logo SVG n'est dessiné qu'en SVG.
