<?php

namespace App\Jobs;

use App\Services\AnalyticsClassifier;
use App\Services\GeoIpService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class IngestClickEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $linkId,
        public CarbonImmutable $ts,
        public ?string $referrer,
        public string $ua,
        public ?string $ip
    ) {}

    public function handle(AnalyticsClassifier $cls, GeoIpService $geo): void
    {
        // Bucket horario (YYYY-MM-DD HH:00:00)
        $hour = $this->ts->setMinute(0)->setSecond(0)->setMicro(0)->toDateTimeString();

        // Features
        $refDomain = (string) $cls->referrerDomain($this->referrer);
        $device    = (string) $cls->deviceClass($this->ua);
        $country   = (string) $geo->countryCode((string) ($this->ip ?? ''));

        // UPSERT acumulativo
        DB::statement(
            'INSERT INTO clicks_agg (link_id, ts_hour, referrer_domain, country_code, device_class, count)
             VALUES (?, ?, ?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE count = count + 1',
            [$this->linkId, $hour, $refDomain, $country, $device]
        );
    }
}
