<?php

$source = __DIR__.'/../public/images/logo-MI.png';
$output = __DIR__.'/../public/icons';

if (! extension_loaded('gd') || ! is_file($source)) {
    fwrite(STDERR, "GD atau logo sumber tidak tersedia.\n");
    exit(1);
}

if (! is_dir($output)) {
    mkdir($output, 0755, true);
}

$image = imagecreatefrompng($source);
imagealphablending($image, true);
imagesavealpha($image, true);
$sourceWidth = imagesx($image);
$sourceHeight = imagesy($image);

foreach ([[192, 'icon-192.png', false], [512, 'icon-512.png', false], [512, 'icon-512-maskable.png', true]] as [$size, $name, $maskable]) {
    $canvas = imagecreatetruecolor($size, $size);
    imagealphablending($canvas, true);
    $background = imagecolorallocate($canvas, 246, 244, 239);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $background);

    $padding = $maskable ? (int) ($size * 0.18) : (int) ($size * 0.08);
    $scale = min(($size - 2 * $padding) / $sourceWidth, ($size - 2 * $padding) / $sourceHeight);
    $width = max(1, (int) round($sourceWidth * $scale));
    $height = max(1, (int) round($sourceHeight * $scale));
    $x = (int) (($size - $width) / 2);
    $y = (int) (($size - $height) / 2);

    imagecopyresampled($canvas, $image, $x, $y, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
    imagepng($canvas, $output.'/'.$name, 9);
    imagedestroy($canvas);
}

imagedestroy($image);
