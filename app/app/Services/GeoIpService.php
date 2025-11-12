<?php

namespace App\Services;

use GeoIp2\Database\Reader;

class GeoIpService
{
    private ?Reader $reader = null;

    public function __construct()
    {
        $db = (string) config('shortener.geoip_db');
        if (is_file($db)) {
            try { $this->reader = new Reader($db); } catch (\Throwable $e) { $this->reader = null; }
        }
    }

    /**
     * Devuelve ISO 3166-1 alpha-2 (ej: "ES") o '' si no resuelve / reservado.
     */
    public function countryCode(string $ip): string
    {
        if (!$this->reader) return '';
        // Filtra privadas/reservadas rápidas
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return '';
        }
        try {
            $rec = $this->reader->country($ip);
            return strtoupper($rec->country->isoCode ?? '') ?: '';
        } catch (\Throwable $e) {
            return '';
        }
    }
}
