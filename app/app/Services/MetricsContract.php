<?php

namespace App\Services;

interface MetricsContract
{
    public function counterInc(string $name, array $labels = [], float|int $value = 1): void;

    public function histogramObserve(string $name, float $seconds, array $labels = [], ?array $buckets = null): void;
}
