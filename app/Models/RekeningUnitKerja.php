<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class RekeningUnitKerja extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_rekening_unit_kerja';

    protected $fillable = [
        'rekening_id',
        'tanggal',
        'value'
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function rekening()
    {
        return $this->belongsTo(MasterRekening::class, 'rekening_id');
    }
}
