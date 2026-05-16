<?php

namespace App\Exports;

use App\Models\HistoricalOccupancyData;
use App\Models\Prediction;
use App\Models\RoomType;
use App\Services\OccupancyCalculationService;
use App\Services\RecommendationService;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

/**
 * Single clean sheet — manager-friendly occupancy report.
 * No technical model metrics. Just: month, segment, occupancy, rooms, revenue, status, action.
 */
class PredictionSummarySheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    protected array $predictionRows;
    protected array $filters;

    const C_NAVY    = '2E5266';
    const C_BLUE    = '4A7FA5';
    const C_LIGHT   = 'D6E4EC';
    const C_WHITE   = 'FFFFFF';
    const C_GRAY    = 'F5F7FA';
    const C_BORDER  = 'E2E8F0';
    const C_GREEN   = 'D1FAE5';
    const C_GTXT    = '065F46';
    const C_BLUE2   = 'DBEAFE';
    const C_BTXT    = '1E40AF';
    const C_YELLOW  = 'FEF3C7';
    const C_YTXT    = '92400E';
    const C_RED     = 'FEE2E2';
    const C_RTXT    = '991B1B';

    public function __construct(array $predictionRows, array $filters = [])
    {
        $this->predictionRows = $predictionRows;
        $this->filters        = $filters;
    }

    public function title(): string { return 'Laporan Prediksi'; }

    public function columnWidths(): array
    {
        return [
            'A' => 22,  // Bulan
            'B' => 26,  // Kamar / Segmen
            'C' => 18,  // Tingkat Hunian
            'D' => 20,  // Kamar Terisi/Hari
            'E' => 26,  // Estimasi Pendapatan
            'F' => 14,  // Kondisi
            'G' => 44,  // Saran
        ];
    }

    public function array(): array
    {
        $roomTypes  = RoomType::where('is_active', true)->get();
        $rows       = collect($this->predictionRows);
        $recService = app(RecommendationService::class);
        $occService = app(OccupancyCalculationService::class);

        $avgOcc   = $rows->isNotEmpty() ? round($rows->avg('predicted_occupancy_rate'), 1) : 0;
        $totalRev = $rows->sum('predicted_revenue');
        $grouped  = $rows->groupBy('month_key');
        $periods  = $grouped->count();

        // Count by distinct months (not rows)
        $monthAvgOcc = $grouped->map(fn($g) => $g->avg('predicted_occupancy_rate'));
        $highMonths  = $monthAvgOcc->filter(fn($v) => $v >= 55)->count();
        $lowMonths   = $monthAvgOcc->filter(fn($v) => $v < 40)->count();

        $data = [];

        // ── Title rows ────────────────────────────────────────────────────────
        $data[] = ['HOTEL DHARMA — LAPORAN PREDIKSI TINGKAT HUNIAN', '', '', '', '', '', ''];
        $data[] = ['Dibuat: ' . Carbon::now()->isoFormat('D MMMM YYYY, HH:mm') . ' WIB  ·  ' . $periods . ' bulan diprediksi', '', '', '', '', '', ''];
        $data[] = ['', '', '', '', '', '', ''];

        // ── Summary row ───────────────────────────────────────────────────────
        $data[] = ['RINGKASAN', '', '', '', '', '', ''];
        $data[] = ['Rata-rata Hunian', 'Hunian Tinggi (≥55%)', 'Perlu Promosi (<40%)', 'Estimasi Total Pendapatan', '', '', ''];
        $data[] = [
            $avgOcc . '%',
            $highMonths . ' bulan',
            $lowMonths . ' bulan',
            (float) $totalRev,
            '', '', '',
        ];
        $data[] = ['', '', '', '', '', '', ''];

        // ── Column headers ────────────────────────────────────────────────────
        $data[] = ['Bulan', 'Kamar / Segmen', 'Tingkat Hunian (%)', 'Kamar Terisi / Hari', 'Estimasi Pendapatan (Rp)', 'Kondisi', 'Rekomendasi'];

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($grouped as $monthKey => $monthRows) {
            $monthLabel = Carbon::createFromFormat('Y-m', $monthKey)->isoFormat('MMMM YYYY');

            foreach ($monthRows as $p) {
                $occ     = (float) ($p['predicted_occupancy_rate'] ?? 0);
                $rooms   = (int)   ($p['predicted_rooms_occupied'] ?? 0);
                $revenue = (float) ($p['predicted_revenue'] ?? 0);
                $segment = $p['room_type_name'] ?? 'Keseluruhan Hotel';

                // Get previous occupancy for trend-aware recommendation
                $prevOcc   = 0.0;
                $predModel = Prediction::find($p['id'] ?? 0);
                if ($predModel) {
                    $prevMonth = Carbon::parse($predModel->getRawOriginal('predicted_for_date'))->subMonth();
                    $prev = Prediction::where('model_type', $predModel->model_type)
                        ->whereYear('predicted_for_date', $prevMonth->year)
                        ->whereMonth('predicted_for_date', $prevMonth->month)
                        ->when($predModel->room_type_id, fn($q) => $q->where('room_type_id', $predModel->room_type_id))
                        ->orderByDesc('created_at')->first();
                    if ($prev) {
                        $prevOcc = (float) $prev->predicted_occupancy_rate;
                    } else {
                        $hist = HistoricalOccupancyData::whereYear('date', $prevMonth->year)
                            ->whereMonth('date', $prevMonth->month)
                            ->when($predModel->room_type_id, fn($q) => $q->where('room_type_id', $predModel->room_type_id))
                            ->get();
                        if ($hist->isNotEmpty()) {
                            $prevOcc = (float) $occService->calculateWeightedOccupancy($hist, $roomTypes);
                        }
                    }
                }

                $rec    = $recService->getRecommendation($occ, $prevOcc);
                $status = $occ >= 55 ? 'Tinggi' : ($occ >= 40 ? 'Sedang' : 'Rendah');

                $data[] = [
                    $monthLabel,
                    $segment,
                    $occ,
                    $rooms,
                    $revenue,
                    $status,
                    $rec['action'] . ' — ' . $rec['detail'],
                ];
            }
        }

        // ── Notes ─────────────────────────────────────────────────────────────
        $data[] = ['', '', '', '', '', '', ''];
        $data[] = ['Catatan:', '', '', '', '', '', ''];
        $data[] = ['• Tinggi (≥55%): Hunian ramai, pertimbangkan penyesuaian harga ke atas', '', '', '', '', '', ''];
        $data[] = ['• Sedang (40–54%): Hunian normal, jaga kualitas layanan', '', '', '', '', '', ''];
        $data[] = ['• Rendah (<40%): Aktifkan kampanye promosi dan sesuaikan harga', '', '', '', '', '', ''];
        $data[] = ['• Laporan ini bersifat perkiraan. Gunakan sebagai acuan perencanaan.', '', '', '', '', '', ''];

        return $data;
    }


    public function styles(Worksheet $sheet): void
    {
        $highestRow = $sheet->getHighestRow();

        // Row 1 — main title
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => self::C_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER, 'indent' => 1],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);

        // Row 2 — date
        $sheet->mergeCells('A2:G2');
        $sheet->getStyle('A2')->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => self::C_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_BLUE]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);

        // Row 4 — RINGKASAN header
        $sheet->mergeCells('A4:G4');
        $sheet->getStyle('A4')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['rgb' => self::C_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'indent' => 1],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(20);

        // Row 5 — summary column headers
        $sheet->getStyle('A5:D5')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => self::C_NAVY]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_LIGHT]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // Row 6 — summary values
        $sheet->getStyle('A6')->getNumberFormat()->setFormatCode('0.0"%"');
        $sheet->getStyle('D6')->getNumberFormat()->setFormatCode('"Rp "#,##0');
        $sheet->getStyle('A6:D6')->applyFromArray([
            'font'      => ['bold' => true, 'size' => 13, 'color' => ['rgb' => self::C_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(6)->setRowHeight(24);

        // Row 8 — column headers for data table
        $sheet->getStyle('A8:G8')->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => self::C_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_NAVY]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(8)->setRowHeight(30);

        // Data rows: 9 onwards until empty A
        $dataStart = 9;
        $dataEnd   = $dataStart;
        $stripe    = 0;

        for ($r = $dataStart; $r <= $highestRow; $r++) {
            $val = (string) $sheet->getCell("A{$r}")->getValue();
            if (empty(trim($val))) break;
            $dataEnd = $r;
            $stripe++;

            // Zebra
            $bg = $stripe % 2 === 1 ? self::C_GRAY : self::C_WHITE;
            $sheet->getStyle("A{$r}:G{$r}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);

            // Occupancy %
            $sheet->getStyle("C{$r}")->getNumberFormat()->setFormatCode('0.0"%"');
            $sheet->getStyle("C{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Rooms
            $sheet->getStyle("D{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Revenue
            $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
            $sheet->getStyle("E{$r}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Status color
            $status = (string) $sheet->getCell("F{$r}")->getValue();
            [$bg2, $fg2] = match($status) {
                'Tinggi' => [self::C_GREEN,  self::C_GTXT],
                'Sedang' => [self::C_BLUE2,  self::C_BTXT],
                'Cukup'  => [self::C_YELLOW, self::C_YTXT],
                default  => [self::C_RED,    self::C_RTXT],
            };
            $sheet->getStyle("F{$r}")->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => $fg2]],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg2]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            // Wrap tip
            $sheet->getStyle("G{$r}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($r)->setRowHeight(28);
        }

        // Borders on data table
        if ($dataEnd >= $dataStart) {
            $sheet->getStyle("A8:G{$dataEnd}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN,   'color' => ['rgb' => self::C_BORDER]],
                    'outline'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => self::C_NAVY]],
                ],
            ]);
        }

        // Notes section
        for ($r = $dataEnd + 1; $r <= $highestRow; $r++) {
            $val = (string) $sheet->getCell("A{$r}")->getValue();
            $sheet->mergeCells("A{$r}:G{$r}");
            if ($val === 'Catatan:') {
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => self::C_NAVY]],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::C_LIGHT]],
                ]);
            } elseif (str_starts_with($val, '•')) {
                $sheet->getStyle("A{$r}")->applyFromArray([
                    'font' => ['size' => 8, 'italic' => true, 'color' => ['rgb' => '555555']],
                ]);
            }
        }

        $sheet->getDefaultRowDimension()->setRowHeight(18);
    }
}
