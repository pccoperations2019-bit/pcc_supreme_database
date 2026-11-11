<?php

namespace App\Http\Controllers\Xhr;

use App\Http\Controllers\Controller;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FileGeneratePresignedUrl extends Controller
{
    /**
     * Generate an upload URL for file upload.
     */
    public function generatePresignedUploadUrl(Request $request)
    {
        $allowedExts = config('documents.allowed_extensions', ['pdf', 'xlsx', 'xls', 'csv']);
        $allowedMimes = config('documents.allowed_mimetypes', []);

        $request->validate([
            'filename' => ['required', 'string', 'max:255'],
            // contentType may be empty (Safari/old browsers for xlsx) — validate only if present
            'contentType' => ['nullable', 'string', 'max:255'],
        ]);

        $filename = $request->input('filename');
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, $allowedExts, true)) {
            return response()->json([
                'message' => 'File type not allowed. Allowed: '.implode(', ', $allowedExts),
                'allowed' => $allowedExts,
            ], 422);
        }

        $contentType = (string) $request->input('contentType', '');
        // If client sent a mime, ensure it is allowlisted; empty is allowed and will be inferred from extension.
        if ($contentType !== '' && $allowedMimes !== [] && ! in_array($contentType, $allowedMimes, true)) {
            // Some browsers send charset suffix e.g. text/csv;charset=utf-8 — normalize
            $baseMime = strtolower(explode(';', $contentType)[0]);
            $baseMime = trim($baseMime);
            if (! in_array($baseMime, array_map('strtolower', $allowedMimes), true)) {
                return response()->json([
                    'message' => 'MIME type not allowed: '.$contentType,
                    'allowed_mimes' => $allowedMimes,
                ], 422);
            }
        }

        $key = 'files/'.Str::uuid().'/'.$request->filename;

        $uploadUrl = url('/xhr/files-upload').'?key='.rawurlencode($key);

        return response()->json([
            'key' => $key,
            'url' => $uploadUrl,
        ]);
    }
}
