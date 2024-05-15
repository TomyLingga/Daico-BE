<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class Setting extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'settings';

    protected $fillable = [
        'setting_name',
        'setting_value'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
