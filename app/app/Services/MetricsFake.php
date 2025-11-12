<?php

namespace App\Services;

final class MetricsFake implements MetricsContract
{
    /** Contadores en memoria para tests */
    public array $counters = [];
    /** Buckets de histograma */
    public array $hist_buckets = [];   // [$name][$le][$key] = int
    public array $hist_sum = [];       // [$name][$key] = float
    public array $hist_count = [];     // [$name][$key] = int

    private function key(string $name, array $labels): string
    {
        ksort($labels);
        return $name.'|'.json_encode($labels, JSON_UNESCAPED_SLASHES);
    }

    public function counterInc(string $name, array $labels = [], int|float $value = 1): void
    {
        $k = $this->key($name, $labels);
        $this->counters[$k] = ($this->counters[$k] ?? 0) + $value;
    }

    public function histogramObserve(string $name, float $seconds, array $labels = [], array $buckets = null): void
    {
        $buckets ??= [0.005,0.01,0.025,0.05,0.1,0.25,0.5,1,2.5,5];
        sort($buckets, SORT_NUMERIC);

        $k = $this->key($name, $labels);

        foreach ($buckets as $le) {
            if ($seconds <= $le) {
                $this->hist_buckets[$name][$le][$k] = ($this->hist_buckets[$name][$le][$k] ?? 0) + 1;
            }
        }

        $this->hist_buckets[$name]['Inf'][$k] = ($this->hist_buckets[$name]['Inf'][$k] ?? 0) + 1;
        $this->hist_sum[$name][$k]   = ($this->hist_sum[$name][$k]   ?? 0) + $seconds;
        $this->hist_count[$name][$k] = ($this->hist_count[$name][$k] ?? 0) + 1;
    }
}
