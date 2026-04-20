<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;

#[Fillable(['email', 'password', 'github_id', 'api_token'])]
#[Hidden(['password', 'remember_token', 'api_token'])]
class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * Автоматическая генерация API-токена при создании пользователя.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (!$user->api_token) {
                $user->api_token = Str::random(60);
            }
        });
    }

    /**
     * Настройка приведения типов.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
