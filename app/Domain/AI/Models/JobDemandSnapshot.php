<?php

namespace App\Domain\AI\Models;

use Illuminate\Database\Eloquent\Model;

class JobDemandSnapshot extends Model
{
    protected $table = 'jobdemandsnapshot';

    protected $primaryKey = 'SnapshotID';

    public $timestamps = false;

    protected $fillable = [
        'JobTitle',
        'industry_id',
        'city_name',
        'AverageSalary',
        'PostCount',
        'SnapshotDate',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'AverageSalary' => 'float',
            'PostCount' => 'integer',
            'SnapshotDate' => 'date',
        ];
    }
}
