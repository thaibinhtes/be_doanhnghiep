<?php

namespace App\Support;

use Illuminate\Validation\Rules\File;

class ImportFileRules
{
    /**
     * Validation rules for Excel/CSV import uploads.
     *
     * @return array<int, mixed>
     */
    public static function excel(): array
    {
        $maxMb = max(1, (int) config('upload.max_mb', 520));

        return [
            'required',
            File::types(['xlsx', 'xls', 'csv'])->max($maxMb * 1024),
        ];
    }
}
