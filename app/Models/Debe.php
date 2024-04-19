<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Debe extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'debe';

    protected $fillable = [
        'coa',
        'id_category3',
        'id_m_report',
        'id_c_centre',
        'id_plant',
        'id_allocation'
    ];

    public function cat3()
    {
        return $this->belongsTo(Category3::class, 'id_category3');
    }
    public function mReport()
    {
        return $this->belongsTo(mReport::class, 'id_m_report');
    }
    public function cCentre()
    {
        return $this->belongsTo(cCentre::class, 'id_c_centre');
    }
    public function plant()
    {
        return $this->belongsTo(Plant::class, 'id_plant');
    }
    public function allocation()
    {
        return $this->belongsTo(Allocation::class, 'id_allocation');
    }
    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
    // public function fairValues()
    // {
    //     return $this->hasMany(FairValue::class, 'id_fixed_asset');
    // }
}
