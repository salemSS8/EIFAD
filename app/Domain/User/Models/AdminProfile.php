<?php

namespace App\Domain\User\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProfile extends Model
{
    use HasFactory;

    protected $table = 'adminprofile';

    protected $primaryKey = 'AdminID';

    public $incrementing = false;

    protected $fillable = [
        'AdminID',
        'EmployeeID',
        'Department',
        'Position',
        'Permissions',
        'InternalNotes',
    ];

    protected function casts(): array
    {
        return [
            'Permissions' => 'json',
        ];
    }

    /**
     * Get the user that owns the admin profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'AdminID', 'UserID');
    }
}
