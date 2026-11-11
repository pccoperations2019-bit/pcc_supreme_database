<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileFetcherController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke($path, $filename)
    {
        $storageKey = sprintf('%s/%s', rtrim($path, '/'), $filename); // Combine path and filename
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('dpm');

        if (! $disk->exists($storageKey)) {
            return response()->json(['error' => 'File not found.'], 404);
        }

        return redirect()->away($disk->url($storageKey));
    }
}
