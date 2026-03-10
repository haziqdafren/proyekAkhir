<?php

namespace App\Exports;

use App\Models\Prediction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Carbon\Carbon;

class PredictionsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
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
        $query = Prediction::with('roomType')->orderBy('predicted_for_date');

        // Check if we have multi-select predictions array
        if (!empty($this->filters['predictions']) && is_array($this->filters['predictions'])) {
            // Build query for multiple predictions (OR conditions for each period)
            $predictions = $this->filters['predictions'];
            
            $query->where(function ($q) use ($predictions) {
                foreach ($predictions as $prediction) {
                    $q->orWhere(function ($subQ) use ($prediction) {
                        $subQ->where('predicted_for_date', '>=', $prediction['start_date'])
                             ->where('predicted_for_date', '<=', $prediction['end_date'])
                             ->where('model_type', $prediction['model_type']);
                    });
                }
            });
        } else {
            // Single prediction filters (original logic)
            if (!empty($this->filters['date_start'])) {
                $query->where('predicted_for_date', '>=', $this->filters['date_start']);
            }

            if (!empty($this->filters['date_end'])) {
                $query->where('predicted_for_date', '<=', $this->filters['date_end']);
            }

            if (!empty($this->filters['model_type'])) {
                $query->where('model_type', $this->filters['model_type']);
            }
        }

        // Room types filter applies to both modes
        if (!empty($this->filters['room_types'])) {
            $query->whereIn('room_type_id', $this->filters['room_types']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'Tanggal Prediksi',
            'Bulan Untuk',
            'Tipe Kamar',
            'Okupansi (%)',
            'Kamar Terisi',
            'Total Kamar',
            'Revenue (Rp)',
            'Confidence (%)',
            'Model',
            'Versi Model',
            'MAPE (%)'
        ];
    }

    public function map($prediction): array
    {
        return [
            $prediction->prediction_date ? Carbon::parse($prediction->prediction_date)->format('d/m/Y H:i') : '-',
            $prediction->predicted_for_date ? Carbon::parse($prediction->predicted_for_date)->format('M Y') : '-',
            $prediction->roomType ? $prediction->roomType->name : 'Total Hotel',
            number_format($prediction->predicted_occupancy_rate, 2, ',', '.'),
            $prediction->predicted_rooms_occupied ?? '0',  // Force string to prevent Excel from hiding 0
            $prediction->roomType ? $prediction->roomType->total_rooms : '0',
            number_format($prediction->predicted_revenue, 0, ',', '.'),
            number_format($prediction->confidence_level, 2, ',', '.'),
            strtoupper($prediction->model_type),
            $prediction->model_version ?? '2.0',  // Default to 2.0 (final model)
            $prediction->model_type === 'single' ? '17,18' : '32,39',  // New MAPE values from final models
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
