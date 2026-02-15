<?php

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UserRole Model - Matches `userrole` table in database (pivot table).
 */
class UserRole extends Model
{
    protected $table = 'userrole';
    protected $primaryKey = 'UserRoleID';
    public $timestamps = false;
    public $incrementing = true;

    protected $fillable = [
        'UserID',
        'RoleID',
        'AssignedAt',
    ];

    protected function casts(): array
    {
        return [
            'AssignedAt' => 'datetime',
        ];
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'UserID', 'UserID');
    }

    /**
     * Get the role.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'RoleID', 'RoleID');
    }
}
