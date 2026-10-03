<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['nombre_sistema', 'logo', 'logo_oscuro', 'icono', 'logo_app_movil', 'color_primario', 'revision_identidad'])]
class AjusteSistema extends Model
{
    protected $table = 'ajustes_sistema';
}
