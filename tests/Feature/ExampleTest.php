<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_root_redirects_to_the_main_access_portal(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('acceso.portal'));
    }
}
