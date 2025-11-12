<?php

namespace App\Services;

final class SlugService
{
    private const ALPH = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

    public function base62Encode(int $num): string {
        if ($num === 0) return '0';
        $s = '';
        while ($num > 0) { $s = self::ALPH[$num % 62] . $s; $num = intdiv($num, 62); }
        return $s;
    }

    public function base62Decode(string $str): int {
        $map = array_flip(str_split(self::ALPH));
        $num = 0;
        foreach (str_split($str) as $c) { $num = $num * 62 + $map[$c]; }
        return $num;
    }

    public function sign(int $id, string $url, string $key): string {
        $mac = hash_hmac('sha256', $id.'|'.$url, $key, true);
        return rtrim(strtr(base64_encode(substr($mac, 0, 8)), '+/', '-_'), '=');
    }

    public function makeSlug(int $id, string $url, string $key): array {
        $idB62 = $this->base62Encode($id);
        $sig = $this->sign($id, $url, $key);
        return [$idB62.$sig, $idB62, $sig];
    }

    public function parseSlug(string $slug): array {
        $sig = substr($slug, -11);
        $idB62 = substr($slug, 0, -11);
        return [$idB62, $sig];
    }
}
