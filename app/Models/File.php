<?php

namespace App\Models;

use Database\Factories\FileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    /** @use HasFactory<FileFactory> */
    use HasFactory;

    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
        'uploaded_by',
    ];

    public static function store(string $key, int $userId): self
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('dpm');

        $fileSize = $disk->size($key);
        $mimeType = $disk->mimeType($key);
        $originalName = basename($key);

        $instance = self::firstOrNew([
            'name' => $originalName,
            'path' => $key,
            'type' => $mimeType,
            'size' => $fileSize,
        ]);

        $instance->uploadedBy()->associate($userId);
        $instance->save();

        return $instance;
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function document()
    {
        return $this->hasOne(Document::class);
    }
}
