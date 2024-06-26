<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class MasterBulkProduksi extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'master_bulk_produksi';

    protected $fillable = [
        'name'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function targetReal()
    {
        return $this->morphOne(TargetReal::class, 'productable');
    }

    public function targetRkap()
    {
        return $this->morphOne(TargetRKAP::class, 'productable');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
