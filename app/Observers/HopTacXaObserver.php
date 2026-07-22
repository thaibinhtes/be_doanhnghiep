<?php

namespace App\Observers;

use App\Models\HopTacXa;
use App\Support\ToChucDinhDanhSync;

class HopTacXaObserver
{
    public function created(HopTacXa $hopTacXa): void
    {
        if (! $hopTacXa->da_cap_nhat_dinh_danh) {
            return;
        }

        ToChucDinhDanhSync::syncHopTacXa($hopTacXa, true);
    }

    public function updated(HopTacXa $hopTacXa): void
    {
        if (! $hopTacXa->wasChanged('da_cap_nhat_dinh_danh')) {
            return;
        }

        ToChucDinhDanhSync::syncHopTacXa(
            $hopTacXa,
            (bool) $hopTacXa->da_cap_nhat_dinh_danh,
        );
    }
}
