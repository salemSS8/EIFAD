<?php

namespace App\Domain\Skill\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Language Model - Matches `language` table in database.
 */
class Language extends Model
{
    protected $table = 'language';
    protected $primaryKey = 'LanguageID';
    public $timestamps = false;

    protected $fillable = [
        'LanguageName',
    ];
}
