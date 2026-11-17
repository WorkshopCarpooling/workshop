<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TravelTimePredictor
{
    public static function routeKey(array $points): string
    {
        return 'route-' . substr(hash('sha256', implode('|', array_map(fn ($p) => number_format($p['lat'],4,'.','') . ',' . number_format($p['lng'],4,'.',''), $points))), 0, 20);
    }

    public static function features(float $distance, string $departure, float $previous): array
    {
        $time = strtotime($departure);
        $hour = (int)date('G', $time) + (int)date('i', $time) / 60;
        $day = (int)date('w', $time);
        return [$distance, sin(2*M_PI*$hour/24), cos(2*M_PI*$hour/24), sin(2*M_PI*$day/7), cos(2*M_PI*$day/7), $previous];
    }

    public function predict(string $key, float $distance, string $departure): array
    {
        $history = Schema::hasTable('travel_time_samples')
            ? DB::table('travel_time_samples')->where('route_key', $key)->where('arrived_at', '<', $departure)->orderByDesc('departed_at')->limit(100)->get()->reverse()->values() : collect();
        $baseline = $history->isNotEmpty() ? (float)$history->avg('actual_minutes') : max(1, $distance/42*60);
        $fallback = ['minutes' => round($baseline,1), 'source' => $history->isNotEmpty() ? 'Historical route mean baseline' : 'Distance / 42 km/h baseline', 'samples' => $history->count(), 'lstm' => false];
        $file = storage_path('app/ml/lstm.json');
        if (!is_file($file)) return $fallback + ['reason' => 'No trained model installed.'];
        try {
            $model = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $steps = (int)($model['sequence_length'] ?? 0);
            if (($model['format'] ?? '') !== 'rideutem-lstm-v1' || $steps < 2 || $steps > 32 || $history->count() < $steps || !in_array($key, $model['route_keys'] ?? [], true)) return $fallback + ['reason' => 'Model needs enough measured history for this exact ordered route.'];
            $rows = $history->slice(-$steps)->values(); $sequence = [];
            for ($i=1; $i<$rows->count(); $i++) $sequence[] = self::features((float)$rows[$i]->distance_km, $rows[$i]->departed_at, (float)$rows[$i-1]->actual_minutes);
            $sequence[] = self::features($distance, $departure, (float)$rows->last()->actual_minutes);
            $minutes = self::infer($model, $sequence);
            if (!is_finite($minutes) || $minutes < 1 || $minutes > 600) return $fallback + ['reason' => 'Model prediction outside the supported range.'];
            return ['minutes' => round($minutes,1), 'source' => 'LSTM trained on collected route observations', 'samples' => $history->count(), 'lstm' => true, 'baselineMinutes' => round($baseline,1)];
        } catch (\Throwable $error) { return $fallback + ['reason' => 'Model unavailable or invalid.']; }
    }

    public static function infer(array $m, array $sequence): float
    {
        $units = count($m['recurrent_kernel']);
        if ($units < 1 || $units > 64 || count($m['kernel']) !== 6 || count($m['bias']) !== 4*$units) throw new \InvalidArgumentException('Invalid LSTM shape.');
        $h = $c = array_fill(0, $units, 0.0);
        $sigmoid = fn ($v) => 1 / (1 + exp(-max(-60, min(60, $v))));
        foreach ($sequence as $row) {
            $x = [];
            for ($k=0; $k<6; $k++) $x[$k] = ($row[$k] - $m['feature_mean'][$k]) / max(1e-6, $m['feature_std'][$k]);
            $g = $m['bias'];
            for ($j=0; $j<4*$units; $j++) {
                for ($k=0; $k<6; $k++) $g[$j] += $x[$k]*$m['kernel'][$k][$j];
                for ($k=0; $k<$units; $k++) $g[$j] += $h[$k]*$m['recurrent_kernel'][$k][$j];
            }
            for ($j=0; $j<$units; $j++) {
                $c[$j] = $sigmoid($g[$units+$j])*$c[$j] + $sigmoid($g[$j])*tanh($g[2*$units+$j]);
                $h[$j] = $sigmoid($g[3*$units+$j])*tanh($c[$j]);
            }
        }
        $y = (float)$m['dense_bias'];
        for ($j=0; $j<$units; $j++) $y += $h[$j]*$m['dense_kernel'][$j];
        return $y*$m['target_std']+$m['target_mean'];
    }
}
