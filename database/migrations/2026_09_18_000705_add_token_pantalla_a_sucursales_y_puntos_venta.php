<?php

use App\Models\PuntoVenta;
use App\Models\Sucursal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Sin esta clave, cualquiera que adivine o enumere un ID numérico de
     * sucursal/punto de venta podía llamar directamente al endpoint que
     * genera el QR (/estacion-marcado/.../token) sin estar físicamente en la
     * tienda, obteniendo un token válido para marcar asistencia de forma
     * remota -- rompiendo por completo el propósito del QR físico. Esta
     * clave larga y aleatoria por estación es el secreto que solo debe
     * conocer la pantalla física.
     */
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('token_pantalla', 40)->nullable()->unique()->after('tipo');
        });

        Schema::table('puntos_venta', function (Blueprint $table) {
            $table->string('token_pantalla', 40)->nullable()->unique()->after('nombre');
        });

        // forceFill(), no update(): token_pantalla es intencionalmente NO
        // fillable en los modelos (para que nunca llegue por un formulario),
        // así que un update() normal lo descartaría en silencio por la
        // protección de mass-assignment y el backfill no haría nada.
        Sucursal::whereNull('token_pantalla')->get()->each(function (Sucursal $sucursal) {
            $sucursal->forceFill(['token_pantalla' => Str::random(40)])->save();
        });

        PuntoVenta::whereNull('token_pantalla')->get()->each(function (PuntoVenta $puntoVenta) {
            $puntoVenta->forceFill(['token_pantalla' => Str::random(40)])->save();
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('token_pantalla');
        });

        Schema::table('puntos_venta', function (Blueprint $table) {
            $table->dropColumn('token_pantalla');
        });
    }
};
