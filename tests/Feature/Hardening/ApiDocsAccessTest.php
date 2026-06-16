<?php

declare(strict_types=1);

namespace Tests\Feature\Hardening;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ApiDocsAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_when_accessing_api_docs(): void
    {
        $this->get('/docs/api')->assertRedirect(route('login'));
    }

    public function test_client_can_access_api_docs(): void
    {
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($client)->get('/docs/api')->assertOk();
    }

    public function test_admin_can_access_api_docs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/docs/api')->assertOk();
    }

    public function test_unverified_user_is_redirected_from_api_docs(): void
    {
        $client = User::factory()->unverified()->create(['role' => 'client']);

        $this->actingAs($client)->get('/docs/api')->assertRedirect();
    }
}
