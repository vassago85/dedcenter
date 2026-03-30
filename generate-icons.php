<?php

function createIcon(int $size, string $path, bool $maskable = false): void
{
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    $bg = imagecolorallocate($img, 8, 20, 43);       // #08142b
    $red = imagecolorallocate($img, 225, 6, 0);       // #e10600

    imagefilledrectangle($img, 0, 0, $size - 1, $size - 1, $bg);

    $cx = $size / 2;
    $cy = $size / 2;

    $dotR   = (int) ($size * 0.042);
    $lineW  = (int) ($size * 0.042);
    $inner  = (int) ($size * 0.17);
    $outer  = (int) ($size * 0.27);

    if ($maskable) {
        $inner = (int) ($size * 0.12);
        $outer = (int) ($size * 0.20);
    }

    imagefilledellipse($img, (int) $cx, (int) $cy, $dotR * 2, $dotR * 2, $red);

    imagesetthickness($img, $lineW);
    imageline($img, (int) $cx, (int) ($cy - $outer), (int) $cx, (int) ($cy - $inner), $red);
    imageline($img, (int) $cx, (int) ($cy + $inner), (int) $cx, (int) ($cy + $outer), $red);
    imageline($img, (int) ($cx - $outer), (int) $cy, (int) ($cx - $inner), (int) $cy, $red);
    imageline($img, (int) ($cx + $inner), (int) $cy, (int) ($cx + $outer), (int) $cy, $red);

    imagepng($img, $path);
    imagedestroy($img);

    echo "Created: $path ($size x $size)\n";
}

$dir = __DIR__ . '/public/icons';
if (!is_dir($dir)) mkdir($dir, 0755, true);

createIcon(192, "$dir/icon-192.png");
createIcon(512, "$dir/icon-512.png");
createIcon(512, "$dir/icon-maskable-512.png", true);

echo "Done.\n";
