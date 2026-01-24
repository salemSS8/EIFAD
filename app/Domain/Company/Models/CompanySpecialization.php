<?php

namespace App\Domain\Company\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CompanySpecialization Model - Matches `companyspecialization` table.
 */
class CompanySpecialization extends Model
{
    protected $table = 'companyspecialization';
    protected $primaryKey = 'SpecID';
    public $timestamps = false;

    protected $fillable = [
        'SpecName',
    ];
}
