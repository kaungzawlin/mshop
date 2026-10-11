<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Settings\RuntimeConfigResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MediaController extends Controller
{
    public function __construct(private RuntimeConfigResolver $resolver) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'collection' => ['nullable', 'string', 'max:64'],
        ]);

        $disk = $this->resolver->activeStorageDisk();
        $file = $validated['file'];
        $filename = $file->getClientOriginalName();
        $path = $file->store('uploads/'.now()->format('Y/m'), $disk);

        $media = Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'filename' => $filename,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize() ?: 0,
            'collection' => $validated['collection'] ?? null,
            'uploaded_by' => $request->user()->id,
            'meta' => [
                'extension' => $file->getClientOriginalExtension(),
                'uuid' => (string) Str::uuid(),
            ],
        ]);

        return response()->json([
            'data' => [
                'id' => $media->id,
                'disk' => $media->disk,
                'path' => $media->path,
                'filename' => $media->filename,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->url(),
                'collection' => $media->collection,
            ],
        ], 201);
    }

    public function destroy(Media $medium): JsonResponse
    {
        Storage::disk($medium->disk)->delete($medium->path);
        $medium->delete();

        return response()->json(['ok' => true]);
    }
}
