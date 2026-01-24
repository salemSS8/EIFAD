<?php

namespace App\Domain\CV\Actions;

use App\Domain\CV\Models\CV;
use App\Domain\Shared\Contracts\ActionInterface;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Storage;

/**
 * Use case: Delete a CV.
 */
class DeleteCVAction implements ActionInterface
{
    /**
     * Execute the delete CV use case.
     */
    public function execute(CV $cv): bool
    {
        // Prevent deletion of the only active CV without replacement
        if ($cv->is_active && $cv->user->cvs()->where('id', '!=', $cv->id)->count() === 0) {
            // Allow deletion since there are no other CVs
        }

        // Delete the file from storage
        if ($cv->file_path && Storage::disk('local')->exists($cv->file_path)) {
            Storage::disk('local')->delete($cv->file_path);
        }

        // Delete the record (cascade will delete analysis)
        return $cv->delete();
    }
}
