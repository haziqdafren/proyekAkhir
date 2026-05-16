<?php

namespace App\Exports;

use App\Models\Prediction;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Manager-friendly single-sheet Excel export.
 */
class PredictionsExport implements WithMultipleSheets
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function sheets(): array
    {
        return [new PredictionSummarySheet($this->buildRows(), $this->filters)];
    }

    protected function buildRows(): array
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

        return $query->get()->map(function (Prediction $p) {
            $monthKey = Carbon::parse($p->predicted_for_date)->format('Y-m');
            return [
                'month_key'                => $monthKey,
                'model_type'               => $p->model_type,
                'room_type_code'           => $p->roomType?->code,
                'room_type_name'           => $p->roomType?->name ?? 'Keseluruhan Hotel',
                'predicted_occupancy_rate' => (float) $p->predicted_occupancy_rate,
                'predicted_rooms_occupied' => (int)   $p->predicted_rooms_occupied,
                'predicted_revenue'        => (float) $p->predicted_revenue,
            ];
        })->toArray();
    }
}
