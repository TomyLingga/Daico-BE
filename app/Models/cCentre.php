<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class cCentre extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'c_centre';

    protected $fillable = [
        'nama'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function debe()
    {
        return $this->hasMany(Debe::class, 'id_c_centre');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
