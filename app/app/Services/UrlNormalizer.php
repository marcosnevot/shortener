<?php

namespace App\Services;

final class UrlNormalizer
{
    public function normalize(string $raw): string
    {
        $u = trim($raw);
        if ($u === '') return $u;

    
        if (!preg_match('~^https?://~i', $u)) {
            $u = 'https://' . $u;
        }

        $parts = parse_url($u);
        if (!is_array($parts) || empty($parts['host'])) {
            return $u;
        }

        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $host   = strtolower($parts['host']);
        $path   = $parts['path'] ?? '';
        $query  = $parts['query'] ?? '';

        // ordena la query alfabéticamente y elimina duplicados exactos
        if ($query !== '') {
            parse_str($query, $q);
            ksort($q);
            $query = http_build_query($q, '', '&', PHP_QUERY_RFC3986);
        }

        // reconstruye SIN fragmento, sin puerto por defecto
        $port = $parts['port'] ?? null;
        $authority = $host;
        if ($port && !($scheme === 'http' && $port == 80) && !($scheme === 'https' && $port == 443)) {
            $authority .= ':' . $port;
        }

        $norm = $scheme . '://' . $authority . $path;
        if ($query !== '') $norm .= '?' . $query;

        return $norm;
    }

    public function domain(string $url): string
    {
        $h = parse_url(trim($url), PHP_URL_HOST);
        return is_string($h) ? strtolower($h) : '';
    }
}
