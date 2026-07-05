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
        return [
            'required',
            File::types(['xlsx', 'xls', 'csv'])->max(520 * 1024),
        ];
    }
}
