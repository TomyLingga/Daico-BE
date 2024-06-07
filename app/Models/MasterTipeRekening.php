<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MasterTipeRekening extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_tipe_cash_on_hand';

    protected $fillable = [
        'nama',
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function rekening()
    {
        return $this->hasMany(MasterRekening::class, 'tipe_id');
    }
}
