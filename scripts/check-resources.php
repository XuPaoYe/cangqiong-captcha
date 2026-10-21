<?php
$root = dirname(__DIR__).'/resources/';
$manifest = json_decode(file_get_contents($root.'manifest.json'), true, flags: JSON_THROW_ON_ERROR);
foreach ($manifest as $file => $hash) {
    if (!is_file($root.$file) || hash_file('sha256', $root.$file) !== $hash) {
        throw new RuntimeException('Resource hash mismatch: '.$file);
    }
}
$gd = gd_info();
foreach (['FreeType Support', 'JPEG Support', 'PNG Support'] as $feature) {
    if (!($gd[$feature] ?? false)) throw new RuntimeException('GD requires '.$feature);
}
echo 'Checked '.count($manifest)." resource hashes and GD features.\n";
