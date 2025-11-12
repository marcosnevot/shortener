<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function show(Request $request, int $id)
    {
        $range = $request->query('range', '7d');
        $days = match ($range) {
            '1d' => 1,
            '30d' => 30,
            default => 7
        };

        $k = (int) config('shortener.k_anon', 5);

        $to   = now()->minute(0)->second(0);
        $from = $to->copy()->subDays($days);

        // Serie horaria total
        $series = DB::table('clicks_agg')
            ->select('ts_hour', DB::raw('SUM(`count`) as count'))
            ->where('link_id', $id)
            ->whereBetween('ts_hour', [$from, $to])
            ->groupBy('ts_hour')
            ->orderBy('ts_hour')
            ->get();

        // Breakdown por referrer
        $byRef = DB::table('clicks_agg')
            ->select('referrer_domain as bucket', DB::raw('SUM(`count`) as count'))
            ->where('link_id', $id)
            ->whereBetween('ts_hour', [$from, $to])
            ->groupBy('referrer_domain')
            ->orderByDesc(DB::raw('SUM(`count`)'))
            ->get()
            ->filter(fn($r) => $r->count >= $k)
            ->values();

        // Breakdown por country ('' -> unknown)
        $byCountry = DB::table('clicks_agg')
            ->select(DB::raw("NULLIF(country_code, '') as bucket"), DB::raw('SUM(`count`) as count'))
            ->where('link_id', $id)
            ->whereBetween('ts_hour', [$from, $to])
            ->groupBy('country_code')
            ->orderByDesc(DB::raw('SUM(`count`)'))
            ->get()
            ->map(function ($r) { $r->bucket = $r->bucket ?? 'unknown'; return $r; })
            ->filter(fn($r) => $r->count >= $k)
            ->values();

        // Breakdown por device
        $byDevice = DB::table('clicks_agg')
            ->select('device_class as bucket', DB::raw('SUM(`count`) as count'))
            ->where('link_id', $id)
            ->whereBetween('ts_hour', [$from, $to])
            ->groupBy('device_class')
            ->orderByDesc(DB::raw('SUM(`count`)'))
            ->get()
            ->filter(fn($r) => $r->count >= $k)
            ->values();

        return response()->json([
            'range'      => $range,
            'from'       => $from->toIso8601String(),
            'to'         => $to->toIso8601String(),
            'series'     => $series,
            'by_referrer'=> $byRef,
            'by_country' => $byCountry,
            'by_device'  => $byDevice,
            'k_anon'     => $k,
        ]);
    }
}
