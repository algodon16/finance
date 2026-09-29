<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['name', 'code', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /** Hanapin o idagdag — normalized para iwas doble (spacing/case). */
    public static function findOrAdd(string $name): ?self
    {
        $clean = trim(preg_replace('/\s+/', ' ', $name));
        if ($clean === '') return null;
        $existing = static::all()->first(fn($d) => mb_strtolower($d->name) === mb_strtolower($clean));
        return $existing ?? static::create(['name' => $clean]);
    }
}
