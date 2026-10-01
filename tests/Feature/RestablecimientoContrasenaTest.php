<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RestablecimientoContrasenaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_hashes_the_new_password_and_invalidates_existing_sessions(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('AnteriorSeguro2026!'),
            'remember_token' => 'token-anterior',
        ]);

        DB::table(config('session.table', 'sessions'))->insert([
            'id' => 'sesion-anterior',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);

        $user->restablecerContrasena('NuevaSegura2026!A', actorId: 999);

        $user->refresh();

        $this->assertTrue(Hash::check('NuevaSegura2026!A', $user->password));
        $this->assertNotSame('NuevaSegura2026!A', $user->password);
        $this->assertNotSame('token-anterior', $user->remember_token);
        $this->assertDatabaseMissing(config('session.table', 'sessions'), ['id' => 'sesion-anterior']);
    }
}
