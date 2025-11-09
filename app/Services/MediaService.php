// app/Services/MediaService.php
<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use FFMpeg\FFMpeg;
use FFMpeg\Coordinate\Dimension;

class MediaService
{
    protected $ffmpeg;

    public function __construct()
    {
        $this->ffmpeg = FFMpeg::create([
            'ffmpeg.binaries' => env('FFMPEG_BINARIES', '/usr/bin/ffmpeg'),
            'ffprobe.binaries' => env('FFPROBE_BINARIES', '/usr/bin/ffprobe'),
        ]);
    }

    /**
     * Upload video to S3 and return metadata
     */
    public function uploadVideo(UploadedFile $file, string $directory = 'videos'): array
    {
        $path = $file->store($directory, 's3');
        $url = Storage::disk('s3')->url($path);

        // Get video duration
        $video = $this->ffmpeg->open($file->getRealPath());
        $duration = $video->getFormat()->get('duration');

        // Generate thumbnail
        $thumbnailPath = $this->generateThumbnail($file, $directory);
        $thumbnailUrl = Storage::disk('s3')->url($thumbnailPath);

        return [
            'url' => $url,
            'path' => $path,
            'duration' => (int) $duration,
            'thumbnail_url' => $thumbnailUrl,
            'thumbnail_path' => $thumbnailPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    /**
     * Upload to Vimeo
     */
    public function uploadToVimeo(UploadedFile $file, string $title): string
    {
        $vimeo = new \Vimeo\Vimeo(
            config('services.vimeo.client_id'),
            config('services.vimeo.client_secret'),
            config('services.vimeo.access_token')
        );

        $uri = $vimeo->upload($file->getRealPath(), [
            'name' => $title,
            'privacy' => [
                'view' => 'disable',
                'embed' => 'whitelist'
            ],
        ]);

        // Get video ID from URI
        $videoId = explode('/', $uri)[2];

        return $videoId;
    }

    /**
     * Generate thumbnail from video
     */
    private function generateThumbnail(UploadedFile $file, string $directory): string
    {
        $video = $this->ffmpeg->open($file->getRealPath());
        $frame = $video->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(5));

        $thumbnailName = uniqid() . '.jpg';
        $thumbnailPath = storage_path('app/temp/' . $thumbnailName);

        $frame->save($thumbnailPath);

        // Upload to S3
        $s3Path = $directory . '/thumbnails/' . $thumbnailName;
        Storage::disk('s3')->put($s3Path, file_get_contents($thumbnailPath));

        // Clean up temp file
        unlink($thumbnailPath);

        return $s3Path;
    }

    /**
     * Delete media from storage
     */
    public function deleteMedia(string $path): bool
    {
        return Storage::disk('s3')->delete($path);
    }
}
