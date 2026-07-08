<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Locks the centralized API exception → JSON envelope mapping configured in
 * bootstrap/app.php (withExceptions). Only applies to /api/* requests.
 */
class ApiExceptionHandlerTest extends TestCase
{
    public function test_undefined_api_route_returns_enveloped_404(): void
    {
        $response = $this->getJson('/api/this-route-does-not-exist-'.uniqid());

        $response->assertStatus(404)
            ->assertExactJson([
                'success' => false,
                'message' => 'Data tidak ditemukan',
            ]);
    }

    public function test_unauthenticated_protected_route_returns_enveloped_401(): void
    {
        // /api/bentuk-usaha/list is behind auth:sanctum,web — no acting user here.
        $response = $this->getJson('/api/bentuk-usaha/list');

        $response->assertStatus(401)
            ->assertExactJson([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
    }
}
