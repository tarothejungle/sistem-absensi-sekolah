<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchoolLocation extends Model
{
    protected $fillable = ['nama_lokasi','latitude','longitude','radius_meter','status'];
}
