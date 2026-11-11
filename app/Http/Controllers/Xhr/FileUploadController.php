<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    /**
     * Accept a raw file upload (PUT) and store it on the local filesystem.
     *
     * Expects query param: key=files/<uuid>/<original filename>
     */
    public function __invoke(Request $request)
    {
        $key = (string) $request->query('key', '');
        if ($key === '') {
            return response()->json(['error' => 'Missing key.'], 422);
        }

        // Basic path-traversal guard — presigned keys must be under files/
        if (! str_starts_with($key, 'files/')) {
            return response()->json(['error' => 'Invalid key prefix.'], 422);
        }

        $extension = strtolower(pathinfo($key, PATHINFO_EXTENSION));
        $allowedExts = config('documents.allowed_extensions', ['pdf', 'xlsx', 'xls', 'csv']);
        if ($extension !== '' && ! in_array($extension, $allowedExts, true)) {
            return response()->json([
                'error' => 'File type not allowed: .'.$extension,
                'allowed' => $allowedExts,
            ], 422);
        }

        $maxMb = (int) config('documents.max_size_mb', 20);
        // getContent() is safe for local disk (20 MB). Enforce limit early.
        $contents = $request->getContent();
        if ($contents === '' || $contents === false) {
            return response()->json(['error' => 'Empty upload.'], 422);
        }

        $sizeBytes = strlen($contents);
        if ($sizeBytes > $maxMb * 1024 * 1024) {
            return response()->json([
                'error' => 'File too large. Max '.$maxMb.' MB.',
            ], 413);
        }

        $disk = Storage::disk('dpm');

        $disk->put($key, $contents, [
            'visibility' => 'public',
        ]);

        return response()->json([
            'key' => $key,
            'url' => $disk->url($key),
        ]);
    }
}
