<?php

namespace App\Exports;

use App\Models\RoomType;
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
 * Detail sheet — one sheet per model type (single / multi).
 * Shows full prediction data with insights: occupancy %, rooms, revenue,
 * confidence, staffing level, marketing action, and performance category.
 */
class PredictionDetailSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths
{
    protected array $predictionRows;
    protected string $modelType;     // 'single' | 'multi'
    protected string $sheetTitle;

    const COLOR_PRIMARY      = '2E5266';
    const COLOR_ACCENT       = '4A7FA5';
    const COLOR_WHITE        = 'FFFFFF';
    const COLOR_LIGHT_GRAY   = 'F5F7FA';
    const COLOR_MID_GRAY     = 'E2E8F0';
    const COLOR_GREEN        = 'D1FAE5';
    const COLOR_GREEN_TEXT   = '065F46';
    const COLOR_YELLOW       = 'FEF3C7';
    const COLOR_YELLOW_TEXT  = '92400E';
    // No red — use primary (navy) for low performance per design system
    const COLOR_LOW          = 'DBEAFE';
    const COLOR_LOW_TEXT     = '1E3A5F';

    public function __construct(array $predictionRows, string $modelType)
    {
        $this->predictionRows = array_filter(
            $predictionRows,
            fn ($r) => $r['model_type'] === $modelType
        );
        $this->modelType  = $modelType;
        $this->sheetTitle = $modelType === 'single'
            ? 'Prediksi Keseluruhan Hotel'
            : 'Prediksi Per Tipe Kamar';
    }

    public function title(): string
    {
        return $this->modelType === 'single' ? 'Prediksi Keseluruhan' : 'Per Tipe Kamar';
    }

    public function columnWidths(): array
    {
        if ($this->modelType === 'single') {
            // A=Bulan, B=Hunian, C=Kamar Terisi/Hari, D=Kapasitas, E=Est. Pendapatan, F=Status, G=Rekomendasi
            return ['A'=>22,'B'=>18,'C'=>22,'D'=>16,'E'=>24,'F'=>22,'G'=>42];
        }
        // A=Bulan, B=Tipe Kamar, C=Kapasitas, D=Hunian, E=Kamar/Hari, F=Est. Pendapatan, G=Status, H=Rekomendasi
        return ['A'=>22,'B'=>20,'C'=>12,'D'=>18,'E'=>22,'F'=>24,'G'=>22,'H'=>42];
    }

    public function array(): array
    {
        $roomTypes  = RoomType::where('is_active', true)->get()->keyBy('code');
        $totalRooms = $roomTypes->sum('total_rooms');

        $rows = collect($this->predictionRows);

        // ── Section header ───────────────────────────────────────────────────
        $data = [
            ["HOTEL DHARMA — {$this->sheetTitle}"],
            ["Laporan Prediksi Hunian | " . Carbon::now()->isoFormat('D MMMM YYYY, HH:mm') . ' WIB'],
            [''],
        ];

        // ── Column headers — no technical fields ─────────────────────────────
        if ($this->modelType === 'single') {
            $data[] = [
                'Bulan',
                'Tingkat Hunian (%)',
                'Rata-rata Kamar Terisi/Hari',
                'Total Kapasitas',
                'Estimasi Pendapatan (Rp)',
                'Status',
                'Rekomendasi',
            ];
        } else {
            $data[] = [
                'Bulan',
                'Tipe Kamar',
                'Kapasitas',
                'Tingkat Hunian (%)',
                'Rata-rata Kamar Terisi/Hari',
                'Estimasi Pendapatan (Rp)',
                'Status',
                'Rekomendasi',
            ];
        }

        // ── Data rows ─────────────────────────────────────────────────────────
        foreach ($rows as $p) {
            $occ        = (float)$p['predicted_occupancy_rate'];
            $monthLabel = Carbon::createFromFormat('Y-m', $p['month_key'])->isoFormat('MMMM YYYY');
            $revenue    = (float)$p['predicted_revenue'];
            $rooms      = (int)$p['predicted_rooms_occupied'];
            $status     = $this->statusLabel($occ);
            $action     = $this->actionLabel($occ);

            if ($this->modelType === 'single') {
                $data[] = [
                    $monthLabel,
                    $occ,
                    $rooms,
                    $totalRooms,
                    $revenue,
                    $status,
                    $action,
                ];
            } else {
                $roomCode = $p['room_type_code'] ?? '–';
                $rt       = $roomTypes->get($roomCode);
                $capacity = $rt ? $rt->total_rooms : 0;
                $roomName = $rt ? $rt->name : '–';

                $data[] = [
                    $monthLabel,
                    $roomName,
                    $capacity,
                    $occ,
                    $rooms,
                    $revenue,
                    $status,
                    $action,
                ];
            }
        }

        // ── Per-month summary for multi ──────────────────────────────────────
        if ($this->modelType === 'multi' && $rows->isNotEmpty()) {
            $data[] = [''];
            $data[] = ['RINGKASAN PER BULAN'];
            $data[] = ['Bulan', 'Rata-rata Hunian (%)', 'Estimasi Total Pendapatan (Rp)', 'Rata-rata Kamar Terisi/Hari', '', '', '', ''];

            $grouped = $rows->groupBy('month_key');
            foreach ($grouped as $monthKey => $group) {
                $monthLabel = Carbon::createFromFormat('Y-m', $monthKey)->isoFormat('MMMM YYYY');
                $avgOcc     = round($group->avg('predicted_occupancy_rate'), 2);
                $totalRev   = $group->sum('predicted_revenue');
                $avgRooms   = round($group->avg('predicted_rooms_occupied'), 1);
                $data[] = [$monthLabel, $avgOcc, $totalRev, $avgRooms, '', '', '', ''];
            }
        }

        // ── Trailing notes ────────────────────────────────────────────────────
        $data[] = [''];
        $data[] = ['Panduan Membaca Laporan:'];
        $data[] = ['• Hunian ≥ 55% (Tinggi): Siapkan staf penuh, pertimbangkan penyesuaian harga'];
        $data[] = ['• Hunian 40–54% (Sedang): Operasional normal, pertahankan strategi saat ini'];
        $data[] = ['• Hunian < 40% (Rendah): Aktifkan kampanye promosi dan penyesuaian harga'];
        $data[] = ['• Hunian < 40% (Rendah): Kampanye pemasaran aktif diperlukan'];
        $data[] = ['• Estimasi Pendapatan dihitung dari kamar terisi × harga dasar × jumlah hari'];
        $data[] = ['• Prediksi bersifat estimasi — gunakan sebagai acuan perencanaan, bukan keputusan mutlak'];

        return $data;
    }

    private function statusLabel(float $occ): string
    {
        if ($occ >= 55) return 'Tinggi';
        if ($occ >= 40) return 'Sedang';
        return 'Rendah';
    }

    private function actionLabel(float $occ): string
    {
        if ($occ >= 55) return 'Siapkan staf penuh & pertimbangkan penyesuaian harga ke atas';
        if ($occ >= 40) return 'Pertahankan strategi saat ini & pastikan kualitas layanan';
        return 'Kampanye pemasaran aktif & penyesuaian harga kompetitif diperlukan';
    }

    public function styles(Worksheet $sheet): void
    {
        $C  = self::COLOR_PRIMARY;
        $CA = self::COLOR_ACCENT;
        $W  = self::COLOR_WHITE;
        $G  = self::COLOR_LIGHT_GRAY;
        $MG = self::COLOR_MID_GRAY;

        $highestRow = $sheet->getHighestRow();
        // single: 7 cols (A–G), multi: 8 cols (A–H)
        $lastCol = $this->modelType === 'single' ? 'G' : 'H';

        // ── Row 1: Title ─────────────────────────────────────────────────────
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $W]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $C]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(26);

        // ── Row 2: Subtitle ──────────────────────────────────────────────────
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->getStyle("A2:{$lastCol}2")->applyFromArray([
            'font'      => ['size' => 9, 'italic' => true, 'color' => ['rgb' => $W]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $CA]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        // ── Row 4: Column headers ─────────────────────────────────────────────
        $sheet->getStyle("A4:{$lastCol}4")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $W]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $C]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(4)->setRowHeight(36);

        // Column mappings (no confidence column now)
        $occCol  = $this->modelType === 'single' ? 'B' : 'D';
        $revCol  = $this->modelType === 'single' ? 'E' : 'F';
        $statCol = $this->modelType === 'single' ? 'F' : 'G';

        // ── Data rows: zebra stripe + status color coding ────────────────────
        $dataStartRow = 5;
        $dataEndRow   = $dataStartRow;

        for ($r = $dataStartRow; $r <= $highestRow; $r++) {
            $val = (string)$sheet->getCell("A{$r}")->getValue();
            if (empty(trim($val)) || $val === 'Panduan Membaca Laporan:' || str_starts_with($val, '•') || $val === 'RINGKASAN PER BULAN') {
                break;
            }
            $dataEndRow = $r;

            // Zebra
            $bg = ($r - $dataStartRow) % 2 === 0 ? $G : $W;
            $sheet->getStyle("A{$r}:{$lastCol}{$r}")->getFill()
                ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bg);

            // Status cell color coding
            $status = (string)$sheet->getCell("{$statCol}{$r}")->getValue();
            $this->colorStatusCell($sheet, "{$statCol}{$r}", $status);

            // Number formatting
            $sheet->getStyle("{$occCol}{$r}")->getNumberFormat()->setFormatCode('0.00"%"');
            $sheet->getStyle("{$revCol}{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');

            // Wrap text for recommendation column (last)
            $sheet->getStyle("{$lastCol}{$r}")->getAlignment()->setWrapText(true);
            $sheet->getRowDimension($r)->setRowHeight(32);
        }

        // ── Recap section for multi ───────────────────────────────────────────
        if ($this->modelType === 'multi') {
            for ($r = $dataEndRow + 1; $r <= $highestRow; $r++) {
                $val = (string)$sheet->getCell("A{$r}")->getValue();
                if ($val === 'RINGKASAN PER BULAN') {
                    $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
                    $sheet->getStyle("A{$r}:{$lastCol}{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $W]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $CA]],
                    ]);
                } elseif ($val === 'Bulan') {
                    $sheet->getStyle("A{$r}:D{$r}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => $W]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $C]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                } elseif (!empty(trim($val)) && $val !== 'Panduan Membaca Laporan:' && !str_starts_with($val, '•')) {
                    $sheet->getStyle("B{$r}")->getNumberFormat()->setFormatCode('0.00"%"');
                    $sheet->getStyle("C{$r}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                    $sheet->getStyle("D{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
            }
        }

        // ── Notes section ─────────────────────────────────────────────────────
        for ($r = $dataEndRow + 1; $r <= $highestRow; $r++) {
            $val = (string)$sheet->getCell("A{$r}")->getValue();
            if ($val === 'Panduan Membaca Laporan:') {
                $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => $C]]]);
                $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            } elseif (str_starts_with($val, '•')) {
                $sheet->getStyle("A{$r}")->applyFromArray(['font' => ['size' => 8, 'color' => ['rgb' => '555555'], 'italic' => true]]);
                $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
            }
        }

        // ── Global borders ────────────────────────────────────────────────────
        if ($dataEndRow >= $dataStartRow) {
            $sheet->getStyle("A4:{$lastCol}{$dataEndRow}")->applyFromArray([
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $MG]],
                    'outline'    => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $C]],
                ],
            ]);
        }

        // ── Header border ─────────────────────────────────────────────────────
        $sheet->getStyle("A1:{$lastCol}2")->applyFromArray([
            'borders' => [
                'outline' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => $C]],
            ],
        ]);

        $sheet->getDefaultRowDimension()->setRowHeight(18);
    }

    private function colorStatusCell(Worksheet $sheet, string $cell, string $status): void
    {
        // Design system: no red. Low = primary blue tint.
        [$bg, $fg] = match ($status) {
            'Tinggi' => [self::COLOR_GREEN,  self::COLOR_GREEN_TEXT],
            'Sedang' => ['DCFCE7',           '166534'],
            'Cukup'  => [self::COLOR_YELLOW, self::COLOR_YELLOW_TEXT],
            default  => [self::COLOR_LOW,    self::COLOR_LOW_TEXT],   // Rendah — blue tint
        };

        $sheet->getStyle($cell)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => $fg]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $bg]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }
}
