<?php

namespace App\Domain\Content\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Content Model - Matches `content` table in database.
 * CMS/blog content.
 */
class Content extends Model
{
    protected $table = 'content';
    protected $primaryKey = 'ContentID';
    public $timestamps = false;

    protected $fillable = [
        'AuthorUserID',
        'Title',
        'BodyText',
        'ImagePath',
        'VideoPath',
        'CreatedAt',
        'Status',
    ];

    protected function casts(): array
    {
        return [
            'CreatedAt' => 'datetime',
        ];
    }

    /**
     * Get the author.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\User\Models\User::class, 'AuthorUserID', 'UserID');
    }
}
