# Images

```php
$logo = Image::fromFile('/path/to/logo.png');
$pdf->image($logo, 54, 720, 120, 40);
```

`Image::fromFile()` and `Image::fromBytes()` read JPEG and PNG files.

| File | How it is written |
|---|---|
| JPEG, grey, RGB or CMYK | As it is, decoded by the reader; an Adobe CMYK JPEG with its inverted values |
| PNG without transparency — grey, RGB or indexed, 1 to 16 bits | Its compressed rows as they are, with the predictor the reader undoes |
| PNG with an alpha channel — grey or RGB, 8 or 16 bits | Decoded, then written as a colour image and an alpha mask |

An interlaced PNG is refused: saving it without interlacing makes it placeable. The transparency of an indexed PNG
(`tRNS`) is ignored, and so are colour profiles and gamma.

An image is placed at the size given, whatever its pixels: a 600-pixel-wide logo placed 150 points wide prints at 288
pixels per inch.
