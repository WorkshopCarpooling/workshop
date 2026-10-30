<?php

require __DIR__ . '/../vendor/autoload.php';
$root = dirname(__DIR__) . '/storage/app/ml/';
if (!is_file($root . 'lstm.json') || !is_file($root . 'parity.json')) {
    fwrite(STDERR, "Train on collected observations first; no parity data is installed.\n");
    exit(1);
}
$model = json_decode(file_get_contents($root . 'lstm.json'), true, 512, JSON_THROW_ON_ERROR);
$parity = json_decode(file_get_contents($root . 'parity.json'), true, 512, JSON_THROW_ON_ERROR);
$actual = App\Services\TravelTimePredictor::infer($model, $parity['sequence']);
$error = abs($actual - $parity['expected_minutes']);
if ($error > 0.01) { fwrite(STDERR, "PHP / Keras parity failed: $error minutes.\n"); exit(1); }
echo "PHP / Keras inference agrees within $error minutes.\n";
