<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Plant extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'plant';

    protected $fillable = [
        'nama'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function debe()
    {
        return $this->hasMany(Debe::class, 'id_plant');
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
