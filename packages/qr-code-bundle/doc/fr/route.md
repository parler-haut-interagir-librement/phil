<!-- phil-dev source-sha256=6db2e5910d7be1ae487364842c94e5d7bd824ab0551ce5ea008b1ec2205e9335 -->
# La route

Avec `route.enabled` et `config/routes.php` importé, les codes sont servis en images :

```twig
<img src="{{ qr_code_url('https://example.com/tickets/42') }}" alt="Ticket 42">
<img src="{{ qr_code_url({type: 'phone', number: '+15551234567'}, 'png', 'brand', true) }}" alt="Call us">
```

`qr_code_url(content, format = 'svg', logo = '', absolute = false)` écrit `/qr-code.svg` ou `/qr-code.png` avec la
charge utile et le logo dans la requête, signés sous une clé dérivée du secret de l'application par HKDF-SHA256 sous
`phil_qr_code.image_url` (`_qr_signature`), que le `uri_signer` de FrameworkBundle ne partage pas. La route ne dessine
que ce que l'application a signé : toute autre adresse, ou un code qui ne peut pas être dessiné, est introuvable — la
route ne peut pas servir à dessiner des codes pour quelqu'un d'autre.

Chaque code est dessiné avec les valeurs par défaut de la configuration, et sa réponse :

- est `public`, `immutable` et gardée `cache_max_age` secondes par les navigateurs et les caches partagés, puisqu'une
  adresse donne toujours la même image ;
- a un `ETag` de son format, de son logo et de sa charge utile, et y répond par `304 Not Modified` ;
- est envoyée avec `X-Content-Type-Options: nosniff` et, pour le SVG, `Content-Security-Policy: default-src 'none';
  img-src data:; style-src 'unsafe-inline'`.

La charge utile est dans l'adresse : gardez les secrets hors des codes servis ainsi — un mot de passe Wi-Fi a sa
place dans un `qr_code()` en ligne, pas dans une URL que les journaux et les caches conservent.
