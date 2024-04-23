<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class actualIncomingCpo extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'actual_in_cpo';

    protected $fillable = [
        'tanggal',
        'qty',
        'harga',
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
