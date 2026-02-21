<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MetaController extends Controller
{
    public function options(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized', 'details' => []], 401);
        }

        return response()->json([
            'data' => config('migraine.options', []),
        ]);
    }
}
