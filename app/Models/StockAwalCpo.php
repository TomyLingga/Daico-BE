<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class StockAwalCpo extends Model
{
    use HasFactory, Notifiable, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'stock_awal_cpo';

    protected $fillable = [
        'tanggal',
        'qty',
        'harga'
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
