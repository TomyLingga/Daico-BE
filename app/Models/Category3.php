<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Category3 extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'category3';

    protected $fillable = [
        'nama',
        'id_category2'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function debe()
    {
        return $this->hasMany(Debe::class, 'id_category3');
    }

    public function cat2()
    {
        return $this->belongsTo(Category2::class, 'id_category2');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
