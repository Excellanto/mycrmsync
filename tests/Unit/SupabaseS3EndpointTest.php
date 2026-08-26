<?php

namespace Tests\Unit;

use App\Services\Integrations\StorageConfigService;
use PHPUnit\Framework\TestCase;

final class SupabaseS3EndpointTest extends TestCase
{
    public function test_converts_project_url_to_storage_s3_endpoint(): void
    {
        $this->assertSame(
            'https://yrzrqavwiwzclemqvhvh.storage.supabase.co/storage/v1/s3',
            StorageConfigService::s3EndpointFromProjectUrl('https://yrzrqavwiwzclemqvhvh.supabase.co')
        );
    }

    public function test_keeps_storage_hostname_and_appends_s3_path(): void
    {
        $this->assertSame(
            'https://abc.storage.supabase.co/storage/v1/s3',
            StorageConfigService::s3EndpointFromProjectUrl('https://abc.storage.supabase.co')
        );
    }

    public function test_does_not_duplicate_s3_path(): void
    {
        $endpoint = 'https://abc.storage.supabase.co/storage/v1/s3';

        $this->assertSame($endpoint, StorageConfigService::s3EndpointFromProjectUrl($endpoint.'/'));
    }

    public function test_keeps_local_supabase_host_and_port(): void
    {
        $this->assertSame(
            'http://127.0.0.1:54321/storage/v1/s3',
            StorageConfigService::s3EndpointFromProjectUrl('http://127.0.0.1:54321')
        );
    }
}
