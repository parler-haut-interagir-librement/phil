<!-- phil-dev source-sha256=8f54c9721b8f62a71d96cd5899a03c654e897160795b1c9fb846c5a3147ca898 -->
# Le composant

Avec Symfony UX Twig Component, `<twig:PhilQrCode:QrCode>` dessine un code accessible :

```twig
<twig:PhilQrCode:QrCode content="https://example.com/tickets/42" />
<twig:PhilQrCode:QrCode :content="{type: 'wifi', ssid: 'Gnome Café', password: 'tea-and-dice'}" alt="Join the café's network" size="{{ 200 }}" />
<twig:PhilQrCode:QrCode content="{{ code }}" :caption="false" logo="brand" class="ticket__code" />
```

```html
<figure class="phil-qr-code">
    <svg aria-hidden="true" …>…</svg>
    <figcaption class="phil-qr-code__caption"><a href="https://example.com/tickets/42" rel="noopener">https://example.com/tickets/42</a></figcaption>
</figure>
```

| Prop | Valeur par défaut | |
|---|---|---|
| `content` | `''` | Un texte, ou le tableau d'un contenu typé (voir [Twig](twig.md#contenus-typés)) |
| `alt` | `''` | Le nom de l'image pour les lecteurs d'écran |
| `caption` | `true` | Si ce que contient le code est écrit à côté |
| `size` | celle de la configuration | La largeur et la hauteur de l'image en pixels |
| `level` | celui de la configuration | `L`, `M`, `Q` ou `H` |
| `logo` | `''` | Le nom d'un logo configuré |

Les autres attributs vont sur la `<figure>`, à côté de sa classe `phil-qr-code`.

## Accessibilité

Un code QR ne dit rien à qui ne peut pas le lire. Le composant écrit ce que contient le code — le libellé du contenu
— dans la légende, sous forme de lien quand c'est une adresse web, pour que l'adresse puisse être lue, suivie ou
copiée. Ensuite :

- sans `alt`, la légende nomme la figure et l'image est cachée aux lecteurs d'écran, de sorte que son contenu est lu
  une seule fois ;
- avec `alt`, l'image est nommée par lui — « Join the café's network » — et la légende suit ;
- sans légende ni `alt`, l'image est nommée par le libellé du contenu.

Le libellé ne contient jamais de mot de passe Wi-Fi : écrivez-le à côté du code quand les lecteurs en ont besoin.

Le composant ne donne que la structure et les classes ; l'apparence est celle de l'application, ou de son système de
design.
