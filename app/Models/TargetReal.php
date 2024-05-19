<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class TargetReal extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'target_real';

    protected $fillable = [
        'productable_id',
        'productable_type',
        'tanggal',
        'value',
    ];

    public function productable()
    {
        return $this->morphTo();
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
