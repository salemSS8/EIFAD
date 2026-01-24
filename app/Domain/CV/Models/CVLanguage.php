<?php

namespace App\Domain\CV\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CVLanguage Model - Matches `cvlanguage` table in database.
 */
class CVLanguage extends Model
{
    protected $table = 'cvlanguage';
    protected $primaryKey = 'CVLanguageID';
    public $timestamps = false;

    protected $fillable = [
        'CVID',
        'LanguageID',
        'LanguageLevel',
    ];

    /**
     * Get the CV this language belongs to.
     */
    public function cv(): BelongsTo
    {
        return $this->belongsTo(CV::class, 'CVID', 'CVID');
    }

    /**
     * Get the language definition.
     */
    public function language(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Skill\Models\Language::class, 'LanguageID', 'LanguageID');
    }
}
