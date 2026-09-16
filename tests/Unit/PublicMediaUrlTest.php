<?php

namespace Tests\Unit;

use App\Support\PublicMediaUrl;
use Tests\TestCase;

class PublicMediaUrlTest extends TestCase
{
    public function test_builds_absolute_laravel_media_urls_from_stored_paths(): void
    {
        config(['branding.media_origin' => 'https://admin.sadbhavnadham.org']);

        $this->assertSame(
            'https://admin.sadbhavnadham.org/storage/causes/hero/test.jpg',
            PublicMediaUrl::fromStoredPath('storage/causes/hero/test.jpg'),
        );

        $this->assertSame(
            'https://admin.sadbhavnadham.org/storage/causes/hero/test.jpg',
            PublicMediaUrl::fromStoredPath('/donate-images/storage/causes/hero/test.jpg'),
        );

        $this->assertSame(
            'https://admin.sadbhavnadham.org/storage/causes/hero/test.jpg',
            PublicMediaUrl::fromStoredPath('https://donate.sadbhavnadham.org/storage/causes/hero/test.jpg'),
        );
    }
}
