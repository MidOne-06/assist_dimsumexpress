<?php

namespace Tests\Feature;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use Tests\TestCase;

class SeguridadModalTest extends TestCase
{
    public function test_security_resources_expose_only_their_list_routes_for_modal_management(): void
    {
        $this->assertSame(['index'], array_keys(UserResource::getPages()));
        $this->assertSame(['index'], array_keys(RoleResource::getPages()));
    }
}
