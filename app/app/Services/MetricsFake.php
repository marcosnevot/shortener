<?php

namespace App\Services;

class MetricsFake implements MetricsContract
{
    public array $counters = [];
    public array $hists = [];

    public function counterInc(string $name, array $labels = [], float $by = 1.0): void
    {
        $key = $name.'|'.md5(json_encode($labels));
        $this->counters[$key] = ($this->counters[$key] ?? 0) + $by;
    }

    public function histogramObserve(string $name, float $seconds, array $labels = [], array $buckets = null): void
    {
        $key = $name.'|'.md5(json_encode($labels));
        $this->hists[$key][] = $seconds;
    }
}
