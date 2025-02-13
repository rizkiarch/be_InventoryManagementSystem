<?php

namespace App\Exports;

use App\Models\Transaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class TransactionsExport implements FromCollection, WithHeadings, WithMapping
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
        return Transaction::with('item')
            ->whereBetween('created_at', [$this->startDate, $this->endDate])
            ->get();
    }

    public function headings(): array
    {
        return [
            'No',
            'Transaction ID',
            'Item Name',
            'Transaction Type',
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
            $row->item->name,
            $row->type,
            $row->quantity,
            $row->status,
            $row->created_at,
        ];
    }
}
