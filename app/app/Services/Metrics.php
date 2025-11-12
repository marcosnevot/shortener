<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

final class Metrics implements MetricsContract
{
    private function labelKey(array $labels): string
    {
        ksort($labels);
        return rtrim(strtr(base64_encode(json_encode($labels, JSON_UNESCAPED_SLASHES)), '+/', '-_'), '=');
    }

    // ⬇️ Alinear firma con la interfaz: int|float $value = 1
    public function counterInc(string $name, array $labels = [], int|float $value = 1): void
    {
        $lk = $this->labelKey($labels);

        // Redis solo incrementa en float para soportar ambos tipos
        Redis::incrByFloat("metrics:cnt:$name:$lk", (float)$value);

        // Índices para evitar SCAN en /metrics
        Redis::sadd("metrics:idx:cnt:$name", $lk);
        Redis::sadd('metrics:names', "cnt:$name");
    }

    /**
     * Histograma (segundos). Buckets acumulativos.
     * Si no pasas $buckets, se usan estos por defecto.
     */
    public function histogramObserve(string $name, float $seconds, array $labels = [], array $buckets = null): void
    {
        $buckets ??= [0.005,0.01,0.025,0.05,0.1,0.25,0.5,1,2.5,5];
        sort($buckets, SORT_NUMERIC);

        $lk = $this->labelKey($labels);

        foreach ($buckets as $le) {
            if ($seconds <= $le) {
                Redis::incrBy("metrics:hist:$name:$le:$lk", 1);
            }
        }
        Redis::incrBy("metrics:hist:$name:Inf:$lk", 1);
        Redis::incrByFloat("metrics:hist:sum:$name:$lk", $seconds);
        Redis::incrBy("metrics:hist:count:$name:$lk", 1);

        // Índices
        Redis::sadd("metrics:idx:hist:$name", $lk);
        foreach ($buckets as $le) {
            Redis::sadd("metrics:hist:buckets:$name", (string)$le);
        }
        Redis::sadd("metrics:hist:buckets:$name", 'Inf');

        Redis::sadd('metrics:names', "hist:$name");
    }
}
