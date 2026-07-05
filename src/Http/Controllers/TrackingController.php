<?php

namespace Dominservice\FingerprintTracking\Http\Controllers;

use Dominservice\FingerprintTracking\Services\TrackingRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TrackingController extends Controller
{
    public function store(Request $request, TrackingRecorder $recorder): JsonResponse
    {
        $result = $recorder->record($request);

        $response = response()->json([
            'status' => 'OK',
            'fingerprint_ulid' => $result['fingerprint']->public_ulid,
            'tracking_event_ulid' => $result['event']->public_ulid,
        ]);

        $cookie = $recorder->makeFingerprintCookie((string) $result['fingerprint']->value);

        if ($cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }
}
