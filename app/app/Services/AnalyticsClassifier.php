<?php

namespace App\Services;

final class AnalyticsClassifier
{
    public function deviceClass(string $ua): string {
        $ua = strtolower($ua);
        if (str_contains($ua,'bot') || str_contains($ua,'crawl')) return 'bot';
        if (str_contains($ua,'ipad') || str_contains($ua,'tablet')) return 'tablet';
        if (str_contains($ua,'mobile') || str_contains($ua,'iphone') || str_contains($ua,'android')) return 'mobile';
        return 'desktop';
    }

    public function referrerDomain(?string $ref): string {
        if (!$ref) return 'direct';
        $h = parse_url($ref, PHP_URL_HOST);
        return $h ? strtolower($h) : 'direct';
    }
}
