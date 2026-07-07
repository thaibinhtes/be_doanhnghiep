<?php

namespace App\Support;

class TaxExcelColumns
{
    public const TAX_UNIT_COLUMNS = [
        'unitCode' => 'ID đơn vị thuế',
        'unitName' => 'Tên đơn vị thuế',
    ];

    public const COMPANY_TAX_COLUMNS = [
        'taxCode' => 'Mã số thuế doanh nghiệp',
        'taxUnitCode' => 'ID đơn vị thuế',
    ];

    public static function taxUnitColumnLabels(): array
    {
        return self::TAX_UNIT_COLUMNS;
    }

    public static function companyTaxColumnLabels(): array
    {
        return self::COMPANY_TAX_COLUMNS;
    }
}
