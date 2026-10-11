<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:my,en'],
        ]);

        $user = $request->user();
        $user->locale = $validated['locale'];
        $user->save();

        return response()->json([
            'user' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }
}
