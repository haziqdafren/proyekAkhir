<?php

namespace App\Console\Commands;

use App\Models\HistoricalOccupancyData;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Import the full 2021-2025 historical Excel dataset into the database.
 *
 * The Excel file (2021_2025_Clean.xlsx) has a single sheet "Daily_Data" with columns:
 *   Tanggal | Tahun | Bulan | Hari | Hari_Numeric | Kamar_Terjual | Revenue |
 *   Okupansi_Rate | Kamar_STD | Kamar_SPR | Kamar_FMY | Kamar_JS
 *
 * Usage:
 *   php artisan import:historical-excel
 *   php artisan import:historical-excel --file=/absolute/path/to/file.xlsx
 *   php artisan import:historical-excel --dry-run        # preview only, no writes
 *   php artisan import:historical-excel --force          # overwrite existing records
 *   php artisan import:historical-excel --from=2021-01-01 --to=2023-12-31
 */
class ImportHistoricalExcel extends Command
{
    protected $signature = 'import:historical-excel
                            {--file=  : Absolute path to the Excel file}
                            {--dry-run : Parse and preview without writing to the database}
                            {--force  : Overwrite records that already exist in the database}
                            {--from=  : Only import dates on or after this date (YYYY-MM-DD)}
                            {--to=    : Only import dates on or before this date (YYYY-MM-DD)}';

    protected $description = 'Import full 2021-2025 historical Excel data into the database';

    // Capacities per room type (from DB — these are the configured totals)
    private const CAPACITY = [
        'STD' => 32,
        'SPR' => 19,
        'FMY' => 3,
        'JS'  => 2,
    ];

    public function handle(): int
    {
        // ── Resolve file path ─────────────────────────────────────────────────
        $filePath = $this->option('file') ?: base_path('../2021_2025_Clean.xlsx');

        if (!file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            $this->line("Use --file=/absolute/path/to/2021_2025_Clean.xlsx");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force  = (bool) $this->option('force');
        $from   = $this->option('from') ? Carbon::parse($this->option('from')) : null;
        $to     = $this->option('to')   ? Carbon::parse($this->option('to'))   : null;

        $this->info("File : {$filePath}");
        $this->info("Mode : " . ($dryRun ? 'DRY-RUN (no writes)' : ($force ? 'FORCE (overwrite existing)' : 'SAFE (skip existing)')));
        if ($from) $this->info("From : {$from->toDateString()}");
        if ($to)   $this->info("To   : {$to->toDateString()}");
        $this->newLine();

        // ── Load Excel ────────────────────────────────────────────────────────
        $this->line('Loading Excel file...');
        try {
            $spreadsheet = IOFactory::load($filePath);
        } catch (\Exception $e) {
            $this->error('Could not open Excel file: ' . $e->getMessage());
            return self::FAILURE;
        }

        $sheet = $spreadsheet->getSheetByName('Daily_Data')
               ?? $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, false);

        if (empty($rows)) {
            $this->error('Sheet is empty.');
            return self::FAILURE;
        }

        // ── Validate header ───────────────────────────────────────────────────
        $header = array_map('trim', $rows[0]);
        // Expected: Tanggal, Tahun, Bulan, Hari, Hari_Numeric, Kamar_Terjual,
        //           Revenue, Okupansi_Rate, Kamar_STD, Kamar_SPR, Kamar_FMY, Kamar_JS
        $required = ['Tanggal', 'Kamar_Terjual', 'Revenue', 'Kamar_STD', 'Kamar_SPR', 'Kamar_FMY', 'Kamar_JS'];
        foreach ($required as $col) {
            if (!in_array($col, $header)) {
                $this->error("Expected column '{$col}' not found in sheet header.");
                $this->line('Header found: ' . implode(', ', $header));
                return self::FAILURE;
            }
        }

        // Build column index map
        $idx = array_flip($header);

        // ── Parse rows ────────────────────────────────────────────────────────
        $this->line('Parsing rows...');
        $parsed = [];
        $parseErrors = 0;

        foreach (array_slice($rows, 1) as $lineNo => $row) {
            // Skip blank rows
            if (empty($row[$idx['Tanggal']])) continue;

            // Parse date
            $rawDate = $row[$idx['Tanggal']];
            try {
                if ($rawDate instanceof \DateTime) {
                    $date = Carbon::instance($rawDate)->toDateString();
                } else {
                    $date = Carbon::parse($rawDate)->toDateString();
                }
            } catch (\Exception $e) {
                $parseErrors++;
                continue;
            }

            // Apply date filters
            $dateCarbon = Carbon::parse($date);
            if ($from && $dateCarbon->lt($from)) continue;
            if ($to   && $dateCarbon->gt($to))   continue;

            $totalSold    = (float) ($row[$idx['Kamar_Terjual']] ?? 0);
            $totalRevenue = (float) ($row[$idx['Revenue']]       ?? 0);
            $soldSTD      = (float) ($row[$idx['Kamar_STD']]     ?? 0);
            $soldSPR      = (float) ($row[$idx['Kamar_SPR']]     ?? 0);
            $soldFMY      = (float) ($row[$idx['Kamar_FMY']]     ?? 0);
            $soldJS       = (float) ($row[$idx['Kamar_JS']]      ?? 0);

            // Revenue split: proportional to rooms sold per type
            // If total sold = 0, skip revenue allocation
            $roomsData = [
                'STD' => $soldSTD,
                'SPR' => $soldSPR,
                'FMY' => $soldFMY,
                'JS'  => $soldJS,
            ];

            $parsed[$date] = [
                'date'     => $date,
                'rooms'    => $roomsData,
                'total_sold'   => $totalSold,
                'total_revenue'=> $totalRevenue,
            ];
        }

        if (empty($parsed)) {
            $this->warn('No rows matched after filtering.');
            return self::SUCCESS;
        }

        $dates   = array_keys($parsed);
        $minDate = min($dates);
        $maxDate = max($dates);
        $this->info('Rows parsed: ' . count($parsed));
        $this->info("Date range : {$minDate}  →  {$maxDate}");

        // ── Dry-run preview ───────────────────────────────────────────────────
        if ($dryRun) {
            $this->newLine();
            $this->warn('DRY-RUN — no data written. Sample of what would be imported:');
            $sample = array_slice($parsed, 0, 3);
            $previewRows = [];
            foreach ($sample as $date => $d) {
                foreach ($d['rooms'] as $code => $sold) {
                    $cap     = self::CAPACITY[$code] ?? 1;
                    $occRate = round($sold / $cap * 100, 2);
                    $rev     = $d['total_sold'] > 0
                        ? round($d['total_revenue'] * ($sold / $d['total_sold']))
                        : 0;
                    $previewRows[] = [$date, $code, "{$sold}/{$cap}", "{$occRate}%", 'Rp ' . number_format($rev, 0, ',', '.')];
                }
            }
            $this->table(['Date', 'Type', 'Sold/Cap', 'Occ%', 'Revenue'], $previewRows);
            return self::SUCCESS;
        }

        // ── Count existing ────────────────────────────────────────────────────
        $existing = HistoricalOccupancyData::whereIn('date', $dates)->count();
        $this->line("Rows already in DB for these dates: {$existing}");
        $this->newLine();

        if (!$this->confirm('Proceed with import?')) {
            $this->info('Cancelled.');
            return self::SUCCESS;
        }

        // ── Insert ────────────────────────────────────────────────────────────
        $roomTypeIds = RoomType::pluck('id', 'code');

        // Pre-build existing key set for fast lookup
        // Normalize stored dates to YYYY-MM-DD (strip any HH:MM:SS suffix from older imports)
        $existingKeys = HistoricalOccupancyData::whereIn('date', $dates)
            ->get(['date', 'room_type_id'])
            ->mapWithKeys(fn ($r) => [substr((string)$r->date, 0, 10) . "_{$r->room_type_id}" => true]);

        $bar = $this->output->createProgressBar(count($parsed));
        $bar->start();

        $inserted = $updated = $skipped = $errors = 0;

        DB::beginTransaction();
        try {
            foreach ($parsed as $date => $d) {
                foreach ($d['rooms'] as $code => $sold) {
                    $roomTypeId = $roomTypeIds[$code] ?? null;
                    if (!$roomTypeId) { $errors++; continue; }

                    $cap     = self::CAPACITY[$code] ?? 1;
                    $occRate = round($sold / $cap * 100, 2);

                    // Revenue: proportional share of daily total
                    $rev = $d['total_sold'] > 0
                        ? round($d['total_revenue'] * ($sold / $d['total_sold']), 2)
                        : 0.0;

                    $key  = "{$date}_{$roomTypeId}";
                    $data = [
                        'occupancy_rate'  => $occRate,
                        'rooms_available' => $cap,
                        'rooms_occupied'  => (int) $sold,
                        'revenue'         => $rev,
                    ];

                    if (isset($existingKeys[$key])) {
                        if ($force) {
                            HistoricalOccupancyData::where('date', $date)
                                ->where('room_type_id', $roomTypeId)
                                ->update($data);
                            $updated++;
                        } else {
                            $skipped++;
                        }
                    } else {
                        HistoricalOccupancyData::create(array_merge($data, [
                            'date'        => $date,
                            'room_type_id'=> $roomTypeId,
                        ]));
                        $inserted++;
                        $existingKeys[$key] = true;
                    }
                }

                $bar->advance();
            }

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine(2);
            $this->error('Import failed — rolled back. Error: ' . $e->getMessage());
            Log::error('ImportHistoricalExcel failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine(2);

        // ── Summary ───────────────────────────────────────────────────────────
        $this->info('Import complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Inserted (new rows)',       $inserted],
                ['Updated (overwritten)',     $updated],
                ['Skipped (already existed)', $skipped],
                ['Errors (unknown type)',     $errors],
            ]
        );

        $newMin   = HistoricalOccupancyData::min('date');
        $newMax   = HistoricalOccupancyData::max('date');
        $newTotal = HistoricalOccupancyData::count();
        $this->newLine();
        $this->info("DB now contains {$newTotal} rows  ({$newMin}  →  {$newMax})");

        if ($inserted > 0) {
            $this->newLine();
            $this->warn('Next step: retrain the LSTM model so it learns from the full dataset.');
            $this->line('  Go to Data Upload → click "Retrain Model" for both Single and Multi');
        }

        return self::SUCCESS;
    }
}
