<!-- phil-dev source-sha256=6060e74ba1b35a6c05a3b0e1f0ee3a2666a354bf35bd0e52507eae09f7632be0 -->
# La forme d'onde

Une forme d'onde est calculée une fois, au téléversement d'un fichier, et conservée en JSON ; un lecteur la lit et
dessine quelques centaines de barres. Rien de lourd n'atteint l'auditeur.

## Le JSON des crêtes

```json
{"version":2,"channels":1,"sample_rate":8000,"samples_per_pixel":800,"bits":8,"length":3,"data":[-10,12,-128,127,0,0]}
```

| Clé | Valeur |
|---|---|
| `version` | 2 ; un document de version 1, sans cette clé, est lu aussi |
| `channels` | 1 : un canal, le son mélangé |
| `sample_rate` | La fréquence à laquelle les échantillons ont été décodés, en hertz |
| `samples_per_pixel` | Les échantillons de chaque tronçon |
| `bits` | 8 ou 16 : la plage des valeurs, de -128 à 127 ou de -32768 à 32767 |
| `length` | Le nombre de tronçons |
| `data` | Pour chaque tronçon, son échantillon le plus bas puis le plus haut |

La forme est celle qu'écrit l'outil audiowaveform de la BBC, de sorte qu'une forme d'onde qu'il a calculée se lit aussi ;
son code n'est pas utilisé.

`Peaks::toJson()` l'écrit ; `PeaksReader::read()` la lit, en refusant un document de plus de 8 Mio ou d'un million de
tronçons (`LimitExceeded`), et tout ce qui n'a pas cette forme (`InvalidAudio`) : une autre version, plusieurs canaux,
des données qui ne sont pas des paires d'entiers dans la plage des bits, une longueur qui ne correspond pas.

## Calculer les crêtes

`FfmpegAudio::peaks($path, $samplesPerPixel)` demande à ffmpeg les échantillons du fichier, mélangés en un canal à
8000 Hz, en 16 bits signés petit-boutiste, dans un fichier temporaire, puis en calcule les crêtes et le supprime. Avec
les 800 échantillons par tronçon par défaut, une forme d'onde compte dix tronçons par seconde : une heure fait 36 000
tronçons, environ 200 Ko de JSON.

`PeaksComputer` fait seul le calcul, à partir de tout flux de tels échantillons, lu par blocs de 64 Kio : une heure de
son ne tient jamais en mémoire. Un tronçon que la fin du flux laisse court est conservé ; un octet qui termine seul le
flux n'est pas un échantillon et est abandonné. Il refuse de construire plus d'un million de tronçons.

```php
$peaks = new PeaksComputer()->fromStream($pipe, 8_000, 800);
```

## Les barres d'un lecteur

`Peaks::amplitudes($width)` ramène la forme d'onde à `$width` barres, chacune de 0 à 1 arrondie à trois décimales :
l'échantillon le plus fort des tronçons qu'elle couvre, rapporté à la plus grande valeur des bits. Une forme d'onde qui a
moins de tronçons donne moins de barres.

```php
$bars = PeaksReader::read($json)->amplitudes(200);   // [0.094, 1.0, 0.0, …]
```

`phil/audio-bundle` donne ces barres à son contrôleur Stimulus, qui les dessine en SVG.
