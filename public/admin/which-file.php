<?php
header('Content-Type: text/plain; charset=utf-8');
echo "I am: " . __FILE__ . "\n\n";

$entries = scandir(__DIR__);
echo "Contents of " . __DIR__ . " as Apache sees them:\n\n";
foreach ($entries as $e) {
    if ($e === '.' || $e === '..') continue;
    $marker = is_dir(__DIR__ . '/' . $e) ? '[DIR]' : '     ';
    $size = is_file(__DIR__ . '/' . $e) ? filesize(__DIR__ . '/' . $e) : 0;
    echo "  $marker " . str_pad($e, 30) . " " . $size . " bytes\n";
}