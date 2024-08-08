<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class BiayaPenyusutan extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'biaya_penyusutan';

    protected $fillable = [
        'alokasi_id',
        'tanggal',
        'value',
    ];

    public function allocation()
    {
        return $this->belongsTo(Allocation::class, 'alokasi_id');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
