<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Redis;

class MetricsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [];
        $names = Redis::smembers('metrics:names') ?: [];

        // -------- Counters --------
        foreach ($names as $m) {
            if (!str_starts_with($m, 'cnt:')) continue;
            $name = substr($m, 4);
            $lines[] = "# TYPE {$name} counter";

            $labelKeys = Redis::smembers("metrics:idx:cnt:$name") ?: [];
            foreach ($labelKeys as $lk) {
                $val = Redis::get("metrics:cnt:$name:$lk");
                if ($val === null) continue;
                $labels = $this->decodeLabels($lk);
                $lines[] = $this->sample($name, $labels, $val);
            }
        }

        // -------- Histograms --------
        foreach ($names as $m) {
            if (!str_starts_with($m, 'hist:')) continue;
            $name = substr($m, 5);
            $lines[] = "# TYPE {$name} histogram";

            $labelKeys = Redis::smembers("metrics:idx:hist:$name") ?: [];
            $bucketVals = Redis::smembers("metrics:hist:buckets:$name") ?: [];
            // Ordena buckets: numéricos y al final +Inf
            $buckets = array_values(array_filter($bucketVals, fn($v) => $v !== 'Inf'));
            sort($buckets, SORT_NUMERIC);
            $buckets[] = 'Inf';

            foreach ($labelKeys as $lk) {
                $labels = $this->decodeLabels($lk);

                foreach ($buckets as $le) {
                    $cnt = (int) (Redis::get("metrics:hist:$name:$le:$lk") ?? 0);
                    $lbl = array_merge($labels, ['le' => $le === 'Inf' ? '+Inf' : (string)$le]);
                    $lines[] = $this->sample("{$name}_bucket", $lbl, $cnt);
                }

                $sum   = (float) (Redis::get("metrics:hist:sum:$name:$lk") ?? 0);
                $count = (int)   (Redis::get("metrics:hist:count:$name:$lk") ?? 0);
                $lines[] = $this->sample("{$name}_sum",   $labels, $sum);
                $lines[] = $this->sample("{$name}_count", $labels, $count);
            }
        }

        $body = implode("\n", $lines) . "\n";
        return response($body, 200)->header('Content-Type', 'text/plain; version=0.0.4');
    }

    private function decodeLabels(string $b64): array
    {
        $json = base64_decode(strtr($b64, '-_', '+/'));
        return $json ? (array) json_decode($json, true) : [];
    }

    private function sample(string $name, array $labels, $value): string
    {
        $lab = $this->labelsToString($labels);
        return $lab ? "{$name}{$lab} {$value}" : "{$name} {$value}";
    }

    private function labelsToString(array $labels): string
    {
        if (empty($labels)) return '';
        ksort($labels);
        $parts = [];
        foreach ($labels as $k => $v) {
            $v = str_replace(['\\', '"', "\n"], ['\\\\','\\"',''], (string)$v);
            $parts[] = $k.'="'.$v.'"';
        }
        return '{'.implode(',', $parts).'}';
    }
}
