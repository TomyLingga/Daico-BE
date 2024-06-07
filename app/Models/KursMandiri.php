<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class KursMandiri extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'kurs_mandiri';

    protected $fillable = [
        'tanggal',
        'value'
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
