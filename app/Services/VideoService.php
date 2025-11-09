<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class VideoService
{
    protected array $platforms = [
        'youtube' => [
            'pattern' => '/(?:youtube\.com\/(?:watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            'embed_url' => 'https://www.youtube.com/embed/{video_id}',
        ],
        'vimeo' => [
            'pattern' => '/(?:vimeo\.com\/)(\d+)/',
            'embed_url' => 'https://player.vimeo.com/video/{video_id}',
        ],
        'dailymotion' => [
            'pattern' => '/(?:dailymotion\.com\/video\/)([a-zA-Z0-9]+)/',
            'embed_url' => 'https://www.dailymotion.com/embed/video/{video_id}',
        ],
    ];

    /**
     * Parse video URL and return platform, video ID, and embed URL
     */
    public function parseVideoUrl(string $url): array
    {
        foreach ($this->platforms as $platform => $config) {
            if (preg_match($config['pattern'], $url, $matches)) {
                $videoId = $matches[1];
                $embedUrl = str_replace('{video_id}', $videoId, $config['embed_url']);

                return [
                    'platform' => $platform,
                    'video_id' => $videoId,
                    'embed_url' => $embedUrl,
                    'original_url' => $url,
                ];
            }
        }

        throw ValidationException::withMessages([
            'video_url' => ['Invalid video URL. Supported platforms: YouTube, Vimeo, DailyMotion'],
        ]);
    }

    /**
     * Validate video URL
     */
    public function validateVideoUrl(string $url): bool
    {
        try {
            $this->parseVideoUrl($url);
            return true;
        } catch (ValidationException $e) {
            return false;
        }
    }

    /**
     * Get video thumbnail URL
     */
    public function getVideoThumbnail(string $platform, string $videoId): ?string
    {
        return match($platform) {
            'youtube' => "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg",
            'vimeo' => $this->getVimeoThumbnail($videoId),
            'dailymotion' => "https://www.dailymotion.com/thumbnail/video/{$videoId}",
            default => null,
        };
    }

    /**
     * Get Vimeo thumbnail via API
     */
    protected function getVimeoThumbnail(string $videoId): ?string
    {
        try {
            $response = file_get_contents("https://vimeo.com/api/v2/video/{$videoId}.json");
            $data = json_decode($response, true);
            return $data[0]['thumbnail_large'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get video duration (requires API keys for some platforms)
     */
    public function getVideoDuration(string $platform, string $videoId): ?int
    {
        // This would require API integration for accurate durations
        // For now, return null and let instructors manually input duration
        return null;
    }

    /**
     * Check if video exists and is accessible
     */
    public function checkVideoAvailability(string $platform, string $videoId): bool
    {
        $urls = [
            'youtube' => "https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={$videoId}&format=json",
            'vimeo' => "https://vimeo.com/api/v2/video/{$videoId}.json",
            'dailymotion' => "https://api.dailymotion.com/video/{$videoId}",
        ];

        if (!isset($urls[$platform])) {
            return false;
        }

        try {
            $headers = @get_headers($urls[$platform]);
            return $headers && strpos($headers[0], '200') !== false;
        } catch (\Exception $e) {
            return false;
        }
    }
}
