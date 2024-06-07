<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MasterRekening extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_rekening';

    protected $fillable = [
        'nama',
        'nomor',
        'matauang_id',
        'jenis_id',
        'tipe_id',
        'keterangan'
    ];

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }

    public function jenis()
    {
        return $this->belongsTo(MasterJenisRekening::class, 'jenis_id');
    }
    public function tipe()
    {
        return $this->belongsTo(MasterTipeRekening::class, 'tipe_id');
    }

    public function rekeningUnitKerja()
    {
        return $this->hasMany(RekeningUnitKerja::class, 'rekening_id');
    }
}
