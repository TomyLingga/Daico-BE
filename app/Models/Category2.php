<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Model;

class Category2 extends Model
{
    use HasFactory, Notifiable;

    protected $primaryKey = 'id';

    protected $table = 'category2';

    protected $fillable = [
        'nama',
        'id_category1'
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function cat3()
    {
        return $this->hasMany(Category3::class, 'id_category2');
    }

    public function cat1()
    {
        return $this->belongsTo(Category1::class, 'id_category1');
    }

    public function logs()
    {
        return $this->morphMany(Log::class, 'model');
    }
}
