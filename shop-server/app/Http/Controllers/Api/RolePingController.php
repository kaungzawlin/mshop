<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolePingController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'role' => $request->user()->role->value,
        ]);
    }
}
