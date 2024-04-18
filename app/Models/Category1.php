<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Category1 extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'category1';

    protected $fillable = [
        'nama'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    // public function debe()
    // {
    //     return $this->hasMany(Debe::class, 'id_category1');
    // }

    public function cat2()
    {
        return $this->hasMany(Category2::class, 'id_category1');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
