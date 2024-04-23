<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class outstandingCpo extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'outstanding_cpo';

    protected $fillable = [
        'kontrak',
        'supplier',
        'qty',
        'harga',
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
