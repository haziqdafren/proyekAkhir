<?php

namespace App\Exports;

use App\Models\Prediction;
use App\Models\ModelVersion;
use App\Models\RoomType;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Flat CSV export — clean numeric values (no thousand separators),
 * dot as decimal separator so any CSV parser can read the numbers correctly.
 */
class PredictionsCsvExport implements FromCollection, WithHeadings, WithMapping
{
    protected array $filters;
    protected int $totalRooms;

    public function __construct(array $filters = [])
    {
        $this->filters    = $filters;
        $this->totalRooms = RoomType::where('is_active', true)->sum('total_rooms') ?? 0;
    }

    public function collection()
    {
        $query = Prediction::with('roomType')->orderBy('predicted_for_date')->orderBy('model_type');

        if (!empty($this->filters['predictions']) && is_array($this->filters['predictions'])) {
            $predictions = $this->filters['predictions'];
            $query->where(function ($q) use ($predictions) {
                foreach ($predictions as $pred) {
                    $q->orWhere(function ($sq) use ($pred) {
                        $sq->where('predicted_for_date', '>=', $pred['start_date'])
                           ->where('predicted_for_date', '<=', $pred['end_date'])
                           ->where('model_type', $pred['model_type']);
                    });
                }
            });
        } else {
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

        if (!empty($this->filters['room_types'])) {
            $query->whereIn('room_type_id', $this->filters['room_types']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'month',                   // YYYY-MM
            'month_label',             // November 2025
            'model_type',              // single | multi
            'model_version',
            'room_type_code',          // null for single
            'room_type_name',
            'room_capacity',
            'predicted_occupancy_pct', // numeric, e.g. 38.81
            'predicted_rooms_occupied',
            'predicted_revenue_idr',   // numeric integer
            'confidence_pct',          // numeric
            'performance_label',
            'recommendation',
            'prediction_created_at',
        ];
    }

    public function map($p): array
    {
        $occ      = (float) $p->predicted_occupancy_rate;
        $capacity = $p->roomType ? $p->roomType->total_rooms : $this->totalRooms;

        $performance = match (true) {
            $occ >= 55 => 'Tinggi',
            $occ >= 40 => 'Sedang',
            default    => 'Rendah',
        };

        $action = match (true) {
            $occ >= 55 => 'Siapkan staf penuh, pertimbangkan harga premium',
            $occ >= 40 => 'Operasional normal, pantau tren',
            default    => 'Tingkatkan iklan, harga kompetitif',
        };

        return [
            Carbon::parse($p->predicted_for_date)->format('Y-m'),
            Carbon::parse($p->predicted_for_date)->isoFormat('MMMM YYYY'),
            $p->model_type,
            $p->model_version ?? '',
            $p->roomType?->code ?? '',
            $p->roomType?->name ?? 'Total Hotel',
            $capacity,
            round($occ, 2),                          // numeric, dot decimal
            (int) $p->predicted_rooms_occupied,
            (int) round((float) $p->predicted_revenue), // integer IDR
            round((float) $p->confidence_level, 2),
            $performance,
            $action,
            $p->prediction_date
                ? Carbon::parse($p->prediction_date)->format('Y-m-d H:i')
                : '',
        ];
    }
}
