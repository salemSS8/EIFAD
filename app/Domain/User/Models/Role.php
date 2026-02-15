<?php

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Role Model - Matches `role` table in database.
 */
class Role extends Model
{
    protected $table = 'role';
    protected $primaryKey = 'RoleID';
    public $timestamps = false;
    public $incrementing = false;

    protected $fillable = [
        'RoleID',
        'RoleName',
    ];

    /**
     * Get all users with this role.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'userrole',
            'RoleID',
            'UserID'
        )->withPivot('AssignedAt');
    }
}
