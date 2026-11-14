<!-- phil-dev source-sha256=c70af77af5a602f72783e279951172294f6205bbf461955b31a655ebf889cd71 -->
# Installation

```sh
composer require phil/svg-chart
```

Le paquet exige PHP 8.5 ou plus récent, `ext-mbstring`, phil/svg-sanitizer, qui nettoie une image avant qu'elle soit
dessinée en PNG, et phil/fonts, qui lit les polices avec lesquelles elle est dessinée.

## L'extension resvg

Les images PNG exigent l'extension PHP `resvg`, qui est facultative : sans elle, les graphiques sont des images SVG et
`ResvgRasterizer::isAvailable()` est faux. Elle s'installe avec [PIE](https://github.com/php/pie), pour PHP 8.3 à 8.5,
y compris les versions à fils d'exécution sûrs comme celle de FrankenPHP :

```sh
pie install resvg-php/resvg
```

PIE la compile depuis ses sources, ce qui exige une chaîne d'outils Rust 1.85 ou plus récente ; suivez le guide de
l'extension pour la source du moteur qu'elle compile. Installez l'extension dans l'image où l'application est
construite, et gardez à côté de l'application les polices avec lesquelles les images sont dessinées : le moteur ne lit
aucune police du système.
