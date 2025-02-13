<?php

namespace App\Exports;

use App\Models\Stock;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StocksExport implements FromCollection, WithHeadings, WithMapping
{
    protected $startDate;
    protected $endDate;

    public function __construct($startDate, $endDate)
    {
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Stock::with('item')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Stock ID',
            'Item Name',
            'Quantity',
            'Type',
            'Status',
            'Date',
        ];
    }

    public function map($row): array
    {
        static $no = 1;
        return [
            $no++,
            $row->id,
            $row->item->name,
            $row->quantity,
            $row->type,
            $row->status,
            $row->created_at,
        ];
    }
}
