<?php

namespace App\Domain\CV\Actions;

use App\Domain\CV\Models\CV;
use App\Domain\CV\Jobs\AnalyzeCVJob;
use App\Domain\User\Models\User;
use App\Domain\Shared\Contracts\ActionInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Use case: Upload a new CV for a user.
 */
class UploadCVAction implements ActionInterface
{
    /**
     * Execute the upload CV use case.
     */
    public function execute(User $user, UploadedFile $file, bool $setActive = true): CV
    {
        return DB::transaction(function () use ($user, $file, $setActive) {
            // If setting as active, deactivate other CVs
            if ($setActive) {
                $user->cvs()->update(['is_active' => false]);
            }

            // Store the file
            $path = $file->store('cvs/' . $user->id, 'local');

            // Create CV record
            $cv = CV::create([
                'user_id' => $user->id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'is_active' => $setActive,
            ]);

            // Dispatch async AI analysis job
            AnalyzeCVJob::dispatch($cv);

            return $cv;
        });
    }
}
