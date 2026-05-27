<?php

namespace Tests\Unit\Support;

use App\Support\PublicUrl;
use PHPUnit\Framework\TestCase;

class PublicUrlTest extends TestCase
{
    public function test_production_app_url_falls_back_to_devline_when_localhost_is_configured(): void
    {
        $resolved = PublicUrl::resolveAppUrl(
            appUrl: 'http://127.0.0.1:8000',
            publicAppUrl: null,
            laravelAppUrl: null,
            environment: 'production',
        );

        $this->assertSame('https://task.devline.studio', $resolved);
    }

    public function test_production_api_url_falls_back_to_devline_api_when_localhost_is_configured(): void
    {
        $resolved = PublicUrl::resolveApiUrl(
            apiUrl: 'http://localhost:8000/api',
            appUrl: 'http://localhost:8000',
            publicAppUrl: null,
            laravelAppUrl: null,
            environment: 'production',
        );

        $this->assertSame('https://task.devline.studio/api', $resolved);
    }

    public function test_local_environment_keeps_localhost_urls(): void
    {
        $resolved = PublicUrl::resolveStorageUrl(
            appUrl: 'http://localhost:8000',
            publicAppUrl: null,
            laravelAppUrl: null,
            environment: 'local',
        );

        $this->assertSame('http://localhost:8000/storage', $resolved);
    }
}
