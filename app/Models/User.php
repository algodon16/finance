<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'phone_number',
        'phone_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'phone_verified_at' => 'datetime',
        'password' => 'hashed',
        'role' => 'string',
        'is_active' => 'boolean',
    ];

    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function getUnreadNotificationsAttribute()
    {
        return $this->notifications()->unread()->get();
    }

    public function scopeStudent($query)
    {
        return $query->where('role', 'student');
    }

    public function scopeCashier($query)
    {
        return $query->where('role', 'cashier');
    }

    public function scopeAccountant($query)
    {
        return $query->where('role', 'accountant');
    }

    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }
}
