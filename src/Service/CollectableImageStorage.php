<?php

namespace App\Service;

use App\Admin\CollectableType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Keeps the local image files (public/images/...) in sync with collectables
 * edited in the admin: downloads the image URL on save, renames the file when
 * the identifier changes and removes it on delete.
 */
class CollectableImageStorage
{
    private const MAX_BYTES = 10 * 1024 * 1024;

    private string $public_dir;
    private HttpClientInterface $client;

    public function __construct(KernelInterface $kernel)
    {
        $this->public_dir = $kernel->getProjectDir().'/public/';
        $this->client = HttpClient::create([
            'timeout' => 15,
            'max_duration' => 30,
            'headers' => [
                // BrickLink blocks non-browser clients; present a browser UA.
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36',
            ],
        ]);
    }

    /**
     * Image URL for admin thumbnails: the local file when present (with a
     * cache-buster so a replaced image shows up), otherwise the remote URL.
     */
    public function getDisplayUrl(CollectableType $type, object $item): ?string
    {
        $path = $type->getLocalImagePath($item);
        $absolute = $this->public_dir.$path;
        if (is_file($absolute))
        {
            return '/'.implode('/', array_map('rawurlencode', explode('/', $path))).'?v='.filemtime($absolute);
        }
        return $type->getImageUrl($item);
    }

    /**
     * Call after the item was saved. $old_path/$old_url are the values from
     * before an edit (null for a new item). When $old_path_shared is true
     * another item still uses the old file, so it is copied instead of moved.
     *
     * @return string|null a warning for the user when something went wrong
     */
    public function sync(CollectableType $type, object $item, ?string $old_path = null, ?string $old_url = null, bool $old_path_shared = false): ?string
    {
        $new_path = $type->getLocalImagePath($item);
        $url = $type->getImageUrl($item);
        $path_changed = $old_path !== null && $old_path !== $new_path && is_file($this->public_dir.$old_path);

        if ($url && ($url !== $old_url || !is_file($this->public_dir.$new_path)))
        {
            $error = $this->download($url, $new_path);
            if ($error === null)
            {
                if ($path_changed && !$old_path_shared)
                {
                    @unlink($this->public_dir.$old_path);
                }
                return null;
            }
            if ($path_changed)
            {
                // Keep the previous image rather than ending up with none.
                $this->relocate($old_path, $new_path, $old_path_shared);
            }
            return 'Saved, but the image could not be downloaded: '.$error;
        }

        if ($path_changed && !$this->relocate($old_path, $new_path, $old_path_shared))
        {
            return 'Saved, but the image file could not be renamed.';
        }

        return null;
    }

    /**
     * Downloads the image when there is a URL but no local file yet (bulk imports).
     *
     * @return bool|string true when downloaded, false when there was nothing to do, or an error
     */
    public function downloadMissing(CollectableType $type, object $item): bool|string
    {
        $path = $type->getLocalImagePath($item);
        $url = $type->getImageUrl($item);
        if (!$url || is_file($this->public_dir.$path))
        {
            return false;
        }

        return $this->download($url, $path) ?? true;
    }

    public function remove(CollectableType $type, object $item): void
    {
        $absolute = $this->public_dir.$type->getLocalImagePath($item);
        if (is_file($absolute))
        {
            @unlink($absolute);
        }
    }

    private function download(string $url, string $path): ?string
    {
        try
        {
            $response = $this->client->request('GET', $url);
            if ($response->getStatusCode() !== 200)
            {
                return 'the server responded with HTTP '.$response->getStatusCode().'.';
            }
            $content_type = $response->getHeaders()['content-type'][0] ?? '';
            if (!str_starts_with($content_type, 'image/'))
            {
                return 'the URL is not an image.';
            }
            $content = $response->getContent();
        }
        catch (\Throwable $e)
        {
            return 'the URL could not be reached.';
        }

        if (\strlen($content) > self::MAX_BYTES)
        {
            return 'the image is larger than 10 MB.';
        }

        $absolute = $this->public_dir.$path;
        $dir = \dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir))
        {
            return 'the images folder is not writable.';
        }
        // Write to a temp file first so a failed write never leaves half an image.
        $tmp = $absolute.'.tmp';
        if (@file_put_contents($tmp, $content) === false || !@rename($tmp, $absolute))
        {
            @unlink($tmp);
            return 'the images folder is not writable.';
        }

        return null;
    }

    private function relocate(string $old_path, string $new_path, bool $copy): bool
    {
        $from = $this->public_dir.$old_path;
        $to = $this->public_dir.$new_path;

        return $copy ? @copy($from, $to) : @rename($from, $to);
    }
}
