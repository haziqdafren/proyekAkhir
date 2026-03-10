<?php

namespace App\Exports;

use App\Models\HistoricalOccupancyData;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class HistoricalDataExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected $filters;

    public function __construct($filters = [])
    {
        $this->filters = $filters;
    }

    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        $query = HistoricalOccupancyData::with('roomType')->orderBy('date');

        // Apply filters
        if (!empty($this->filters['date_start'])) {
            $query->where('date', '>=', $this->filters['date_start']);
        }

        if (!empty($this->filters['date_end'])) {
            $query->where('date', '<=', $this->filters['date_end']);
        }

        if (!empty($this->filters['room_types'])) {
            $query->whereIn('room_type_id', $this->filters['room_types']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal',
            'Bulan',
            'Tahun',
            'Tipe Kamar',
            'Okupansi (%)',
            'Kamar Terisi',
            'Total Kamar',
            'Revenue (Rp)',
            'Revenue per Kamar (Rp)',
        ];
    }

    public function map($data): array
    {
        $roomsOccupied = $data->roomType
            ? round($data->roomType->total_rooms * ($data->occupancy_rate / 100))
            : 0;

        $revenuePerRoom = $roomsOccupied > 0
            ? $data->revenue / $roomsOccupied
            : 0;

        return [
            Carbon::parse($data->date)->format('d/m/Y'),
            Carbon::parse($data->date)->format('M Y'),
            Carbon::parse($data->date)->format('Y'),
            $data->roomType ? $data->roomType->name : 'Unknown',
            number_format($data->occupancy_rate, 2, ',', '.'),
            $roomsOccupied,
            $data->roomType ? $data->roomType->total_rooms : 0,
            number_format($data->revenue, 0, ',', '.'),
            number_format($revenuePerRoom, 0, ',', '.'),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            // Style the header row
            1 => [
                'font' => ['bold' => true, 'size' => 12],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2E5266']
                ],
                'font' => ['color' => ['rgb' => 'FFFFFF'], 'bold' => true],
            ],
        ];
    }
}
