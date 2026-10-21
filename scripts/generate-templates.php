<?php
/** Cangqiong's own procedural artwork. No input images or upstream resources are read. */
declare(strict_types=1);

$root = dirname(__DIR__).'/resources/templates';
$manifestFile = dirname(__DIR__).'/resources/manifest.json';
$manifest = json_decode(file_get_contents($manifestFile), true, flags: JSON_THROW_ON_ERROR);

function roundedBoxDistance(float $x, float $y, float $left, float $top, float $right, float $bottom, float $radius): float
{
    $dx = abs($x - ($left + $right)/2) - (($right - $left)/2 - $radius);
    $dy = abs($y - ($top + $bottom)/2) - (($bottom - $top)/2 - $radius);
    return hypot(max($dx, 0), max($dy, 0)) + min(max($dx, $dy), 0) - $radius;
}

function shapeDistance(int $shape, float $x, float $y): float
{
    if ($shape === 1) {
        $body = roundedBoxDistance($x, $y, 12, 22, 96, 98, 9);
        $tab = hypot($x - 54, $y - 22) - 15;
        $notch = hypot($x - 96, $y - 60) - 14;
    } else {
        $body = roundedBoxDistance($x, $y, 22, 14, 98, 94, 10);
        $tab = hypot($x - 22, $y - 54) - 15;
        $notch = hypot($x - 60, $y - 14) - 13;
    }
    return max(min($body, $tab), -$notch);
}

function transparentCanvas(): GdImage
{
    $image = imagecreatetruecolor(440, 440);
    imagealphablending($image, false);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagesavealpha($image, true);
    return $image;
}

foreach ([1, 2] as $shape) {
    $images = ['mask'=>transparentCanvas(), 'fixed'=>transparentCanvas(), 'active'=>transparentCanvas()];
    for ($y = 0; $y < 440; $y++) {
        for ($x = 0; $x < 440; $x++) {
            $px = ($x + .5) / 4; $py = ($y + .5) / 4;
            $distance = shapeDistance($shape, $px, $py);
            if ($distance > 0) continue;
            imagesetpixel($images['mask'], $x, $y, imagecolorallocatealpha($images['mask'], 255, 255, 255, 0));
            $depth = -$distance;
            $nx = shapeDistance($shape, $px + .5, $py) - shapeDistance($shape, $px - .5, $py);
            $ny = shapeDistance($shape, $px, $py + .5) - shapeDistance($shape, $px, $py - .5);
            $upperLeft = $nx + $ny <= 0;
            // Recess: dark upper/left wall, subtle lower/right reflected light, translucent floor.
            if ($depth < 1.1 && !$upperLeft) { $rgb = [235, 242, 247]; $alpha = 78; }
            elseif ($depth < 3.2) { $rgb = [8, 17, 26]; $alpha = (int)round(12 + $depth*7); }
            else { $rgb = [13, 25, 36]; $alpha = 57; }
            imagesetpixel($images['fixed'], $x, $y, imagecolorallocatealpha($images['fixed'], ...[...$rgb, $alpha]));
            // Movable piece: keep source texture intact except a narrow neutral bevel.
            if ($depth < 1.0) { $rgb = [250, 253, 255]; $alpha = 12; }
            elseif ($depth < 3.0) { $rgb = $upperLeft ? [250,253,255] : [9,20,30]; $alpha = $upperLeft ? 34 : 58; }
            else continue;
            imagesetpixel($images['active'], $x, $y, imagecolorallocatealpha($images['active'], ...[...$rgb, $alpha]));
        }
    }
    $directory = $root.'/slider_'.$shape;
    if (!is_dir($directory)) mkdir($directory, 0755, true);
    foreach ($images as $name=>$image) {
        $path = $directory.'/'.$name.'.png';
        // Render at 4x, then retain fractional alpha while reducing to protocol size.
        $output = imagecreatetruecolor(110, 110);
        imagealphablending($output, false);
        imagesavealpha($output, true);
        imagecopyresampled($output, $image, 0, 0, 0, 0, 110, 110, 440, 440);
        if (!imagepng($output, $path, 9)) throw new RuntimeException('Cannot write template: '.$path);
        $manifest['templates/slider_'.$shape.'/'.$name.'.png'] = hash_file('sha256', $path);
    }
}
file_put_contents($manifestFile, json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
echo "Generated two original Cangqiong puzzle shapes (110x110), recesses and piece bevels.\n";
