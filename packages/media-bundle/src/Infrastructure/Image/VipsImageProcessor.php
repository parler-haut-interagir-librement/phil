<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Infrastructure\Image;

use Override;
use Phil\MediaBundle\Application\ImageFormat;
use Phil\MediaBundle\Application\ImageProcessor;
use Phil\MediaBundle\Application\Transform\Geometry;
use Phil\MediaBundle\Application\Transform\Step;
use Phil\MediaBundle\Domain\Exception\InvalidMedia;
use Symfony\Component\Process\Process;

/**
 * Renders images with libvips through its command-line tools, `vips` and `vipsheader`, along the same steps as GD:
 * libvips streams an image in tiles instead of decoding it whole, so a large photo is rendered in a fraction of the
 * memory and the time. Each step is a crop (`extract_area`), a scale (`resize`) and a placement on a canvas
 * (`embed`), on images in the native format of libvips in a private temporary directory; the result is saved as WebP,
 * AVIF, JPEG or PNG at the format's quality, without metadata.
 *
 * Only a raster image PHP recognises — JPEG, PNG, GIF, WebP, AVIF — reaches libvips, after the pixel limit, so that
 * none of its other loaders reads what a person uploaded; the tools run without a shell, with the loaders libvips
 * marks untrusted blocked, for a minute at most each.
 */
final readonly class VipsImageProcessor implements ImageProcessor
{
    /** The image types handed to libvips, as `getimagesize()` names them. */
    private const array TYPES = [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_GIF, \IMAGETYPE_WEBP, \IMAGETYPE_AVIF];

    /**
     * @param int<1, max> $maxPixels the largest width times height rendered
     */
    private function __construct(
        public string $vips,
        public string $header,
        private int $maxPixels,
        private float $timeout,
    ) {
    }

    /**
     * @param string      $vips      the path of `vips`
     * @param int<1, max> $maxPixels
     */
    public static function of(string $vips, int $maxPixels = GdImageProcessor::MAX_PIXELS, float $timeout = 60.0): self
    {
        $header = \dirname($vips).'/vipsheader';

        foreach ([$vips, $header] as $binary) {
            if (!is_file($binary) || !is_executable($binary)) {
                throw InvalidMedia::because(\sprintf('"%s" is not an executable file.', $binary));
            }
        }

        return new self($vips, $header, $maxPixels, $timeout);
    }

    /**
     * The processor of the `vips` found in the directories of a `PATH` — the process's by default —, or `null`.
     *
     * @param int<1, max> $maxPixels
     */
    public static function find(int $maxPixels = GdImageProcessor::MAX_PIXELS, ?string $path = null): ?self
    {
        $environment = getenv('PATH');
        $path ??= false === $environment ? '' : $environment;

        foreach (explode(\PATH_SEPARATOR, $path) as $directory) {
            if ('' !== $directory && is_executable($directory.'/vips') && is_executable($directory.'/vipsheader')) {
                return self::of($directory.'/vips', $maxPixels);
            }
        }

        return null;
    }

    #[Override]
    public function render(string $image, ImageFormat $format, array $focalPoint, string $type): string
    {
        if (!\in_array($type, ['webp', 'avif', 'jpeg', 'png'], true)) {
            throw InvalidMedia::because(\sprintf('An image is rendered as webp, avif, jpeg or png; not as "%s".', $type));
        }

        $size = @getimagesizefromstring($image);

        if (false === $size || !\in_array($size[2], self::TYPES, true) || $size[0] < 1 || $size[1] < 1) {
            throw InvalidMedia::because('The file cannot be read as an image.');
        }

        if ($size[0] * $size[1] > $this->maxPixels) {
            throw InvalidMedia::because(\sprintf('An image of %d × %d pixels is larger than the %d pixels rendered at most.', $size[0], $size[1], $this->maxPixels));
        }

        $directory = sys_get_temp_dir().'/phil-media-vips-'.sodium_bin2hex(random_bytes(8));
        mkdir($directory, 0o700);

        try {
            file_put_contents($directory.'/source', $image);
            $file = $this->run($directory, 'colourspace', $directory.'/source', ['srgb']);
            $bands = (int) $this->tool([$this->header, '-f', 'bands', $file]);
            $geometry = Geometry::of($size[0], $size[1], $focalPoint[0], $focalPoint[1]);

            foreach ($format->transformers as $transformer) {
                $step = $transformer->step($geometry);

                if ($step->changes($geometry)) {
                    [$file, $bands] = $this->draw($directory, $file, $bands, $step, $geometry);
                    $geometry = $step->next($geometry);
                }
            }

            if ('jpeg' === $type && 4 === $bands) {
                // JPEG has no alpha: transparent pixels come out black, as GD writes them.
                $file = $this->run($directory, 'flatten', $file, ['--background', '0 0 0']);
            }

            $output = $directory.'/rendered.'.$type;
            $this->tool([$this->vips, 'copy', $file, $output.match ($type) {
                'png' => '[strip]',
                'avif' => \sprintf('[Q=%d,strip,compression=av1]', $format->quality),
                default => \sprintf('[Q=%d,strip]', $format->quality),
            }]);
            $rendered = is_file($output) ? file_get_contents($output) : false;

            if (false === $rendered || '' === $rendered) {
                throw InvalidMedia::because('vips wrote no image.');
            }

            return $rendered;
        } finally {
            $leftovers = glob($directory.'/*');

            foreach (false === $leftovers ? [] : $leftovers as $leftover) {
                unlink($leftover);
            }

            rmdir($directory);
        }
    }

    /**
     * @return array{string, int} the image after the step, and its number of bands
     */
    private function draw(string $directory, string $file, int $bands, Step $step, Geometry $image): array
    {
        if ([$step->sourceLeft, $step->sourceTop, $step->sourceWidth, $step->sourceHeight] !== [0, 0, $image->width, $image->height]) {
            $file = $this->run($directory, 'extract_area', $file, [(string) $step->sourceLeft, (string) $step->sourceTop, (string) $step->sourceWidth, (string) $step->sourceHeight]);
        }

        if ([$step->targetWidth, $step->targetHeight] !== [$step->sourceWidth, $step->sourceHeight]) {
            $file = $this->run($directory, 'resize', $file, [$this->scale($step->targetWidth / $step->sourceWidth), '--vscale', $this->scale($step->targetHeight / $step->sourceHeight)]);
        }

        if ([$step->targetLeft, $step->targetTop, $step->targetWidth, $step->targetHeight] !== [0, 0, $step->canvasWidth, $step->canvasHeight]) {
            if (null === $step->background && 3 === $bands) {
                // What the image leaves uncovered is transparent: the image gets an opaque alpha first.
                $file = $this->run($directory, 'bandjoin_const', $file, ['255']);
                $bands = 4;
            }

            $colour = null === $step->background ? [0, 0, 0, 0] : [$step->background >> 16 & 0xFF, $step->background >> 8 & 0xFF, $step->background & 0xFF, 255];
            $file = $this->run($directory, 'embed', $file, [(string) $step->targetLeft, (string) $step->targetTop, (string) $step->canvasWidth, (string) $step->canvasHeight, '--extend', 'background', '--background', implode(' ', \array_slice($colour, 0, $bands))]);
        }

        return [$file, $bands];
    }

    /**
     * Runs one operation of libvips into a new file of the native format, and returns its path.
     *
     * @param list<string> $arguments
     */
    private function run(string $directory, string $operation, string $input, array $arguments): string
    {
        $output = $directory.'/'.$operation.'-'.sodium_bin2hex(random_bytes(4)).'.v';
        $this->tool([$this->vips, $operation, $input, $output, ...$arguments]);

        return $output;
    }

    /**
     * @param non-empty-list<string> $command
     */
    private function tool(array $command): string
    {
        $process = new Process($command, null, ['VIPS_BLOCK_UNTRUSTED' => '1'], null, $this->timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw InvalidMedia::because(\sprintf('%s %s failed: %s', basename($command[0]), $command[1], trim($process->getErrorOutput())));
        }

        return $process->getOutput();
    }

    private function scale(float $scale): string
    {
        return rtrim(rtrim(\sprintf('%.12F', $scale), '0'), '.');
    }
}
