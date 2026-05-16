<?php

namespace App\Services;

use App\Models\TrainingUpload;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExcelParserService
{
    /**
     * Room type mapping from Excel variations to system codes
     */
    private const ROOM_TYPE_MAP = [
        // Standard rooms
        'STD DBLE' => 'STD',
        'STD. TWIN' => 'STD',
        'STD TWIN' => 'STD',
        'STANDARD' => 'STD',
        
        // Superior rooms
        'SUP DBLE' => 'SPR',
        'SUP DOBL' => 'SPR',
        'SUP TWIN' => 'SPR',
        'SUP  TWIN' => 'SPR',
        'SUP TWIN ' => 'SPR',
        'SUPERIOR' => 'SPR',
        
        // Junior Suite
        'SUITE TWIN' => 'JS',
        'JUNIOR SUITE' => 'JS',
        'JS TWIN' => 'JS',
        
        // Family
        'FAMILY' => 'FMY',
        'FAMILY ROOM' => 'FMY',
    ];

    /**
     * Month name mapping (Indonesian + English).
     * ORDER MATTERS: longer/more-specific keys must come before shorter prefix keys
     * so that str_starts_with matching in normalizeMonthName() picks the right one.
     * e.g. "SEPTEMBER" before "SEPT" before "SEP", "AGUSTUS" before "AGUS" before "AGU".
     */
    private const MONTH_MAP = [
        // January
        'JANUARI'   => 1,  'JANUARY' => 1,  'JAN' => 1,
        // February
        'FEBRUARI'  => 2,  'FEBRUARY' => 2, 'FEB' => 2,
        // March
        'MARET'     => 3,  'MARCH' => 3,    'MAR' => 3,
        // April
        'APRIL'     => 4,  'APR' => 4,
        // May
        'MEI'       => 5,  'MAY' => 5,
        // June
        'JUNI'      => 6,  'JUNE' => 6,     'JUN' => 6,
        // July
        'JULI'      => 7,  'JULY' => 7,     'JUL' => 7,
        // August — longest first so "AGUSTUS" is matched before "AGUS"/"AGU"
        'AGUSTUS'   => 8,  'AUGUST' => 8,   'AGUS' => 8, 'AGU' => 8, 'AUG' => 8,
        // September — longest first
        'SEPTEMBER' => 9,  'SEPT' => 9,     'SEP' => 9,
        // October — longest first
        'OKTOBER'   => 10, 'OCTOBER' => 10, 'OKT' => 10, 'OCT' => 10,
        // November
        'NOVEMBER'  => 11, 'NOV' => 11,
        // December — longest first
        'DESEMBER'  => 12, 'DECEMBER' => 12, 'DES' => 12, 'DEC' => 12,
    ];

    /**
     * Column indices in the Excel file
     */
    private const COL_ROOM = 0;      // RM - Room number
    private const COL_OCCUPIED = 1;  // OC - Occupied flag
    private const COL_PERSONS = 2;   // P - Number of persons
    private const COL_PRICE = 11;    // PRICE
    private const COL_TYPE = 14;     // TYPE (col 14 in actual Excel files)

    /**
     * Parse Excel file for validation only — no database record required.
     *
     * @param string $filePath     Temp/real path on disk (used to load the file)
     * @param string $originalName Original client file name (e.g. "GUEST November 2025.xls")
     *                             Used to extract the authoritative month/year so stray sheets
     *                             and wrong-year sheet tabs are filtered out correctly.
     */
    public function parseForValidation(string $filePath, string $originalName = ''): array
    {
        $log = [];

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheetNames  = $spreadsheet->getSheetNames();

            // 1. Try to get authoritative month/year from the original file name
            $fileMonthYear = $this->parseMonthYearFromFileName($originalName ?: basename($filePath));

            // 2. If file name gave no result, infer the dominant month from sheet names
            if (!$fileMonthYear) {
                $fileMonthYear = $this->inferDominantMonthFromSheets($sheetNames);
            }

            $dailyData      = [];
            $parsedDays     = []; // track which day-numbers we successfully parsed
            $straySheets    = []; // mislabelled sheets: day => ['name' => ..., 'sheet' => ...]
            $totalParsed    = 0;
            $totalFailed    = 0;

            foreach ($sheetNames as $sheetName) {
                $dateInfo = $this->parseSheetNameToDate($sheetName, $fileMonthYear);

                if (!$dateInfo) {
                    // Check if it's a stray sheet (parseable day number but wrong month)
                    $rawInfo = $this->parseSheetNameToDate($sheetName); // no month filter
                    if ($rawInfo && $fileMonthYear && $rawInfo['month'] !== $fileMonthYear['month']) {
                        // Store as a rescue candidate keyed by day number
                        $straySheets[$rawInfo['day']] = [
                            'name'     => $sheetName,
                            'dateInfo' => [
                                'day'   => $rawInfo['day'],
                                'month' => $fileMonthYear['month'],
                                'year'  => $fileMonthYear['year'] ?? $rawInfo['year'] ?? (int) date('Y'),
                            ],
                        ];
                        $log[] = "Stray sheet (wrong month label): {$sheetName} — will rescue if day is missing";
                    } else {
                        $log[] = "Skipping sheet: {$sheetName}";
                    }
                    continue;
                }

                try {
                    $sheet   = $spreadsheet->getSheetByName($sheetName);
                    $dayData = $this->parseSheet($sheet, $dateInfo, $sheetName);

                    if ($dayData) {
                        $dailyData[]            = $dayData;
                        $parsedDays[$dateInfo['day']] = true;
                        $totalParsed++;
                    }
                } catch (\Exception $e) {
                    $totalFailed++;
                    $log[] = "Failed sheet {$sheetName}: {$e->getMessage()}";
                }
            }

            // Rescue pass: for any stray sheet whose day number is not already parsed,
            // treat it as belonging to the authoritative month (staff mislabelled the tab).
            // Only rescue if the day number is valid for that month (e.g. skip day 31 in Nov/Sep).
            $daysInMonth = $fileMonthYear
                ? cal_days_in_month(CAL_GREGORIAN, $fileMonthYear['month'], $fileMonthYear['year'] ?? (int) date('Y'))
                : 31;

            foreach ($straySheets as $day => $candidate) {
                if ($day > $daysInMonth) {
                    $log[] = "Rescue skipped (day {$day} impossible in month {$daysInMonth}-day): {$candidate['name']}";
                    continue;
                }
                if (isset($parsedDays[$day])) {
                    $log[] = "Rescue skipped (day {$day} already parsed): {$candidate['name']}";
                    continue;
                }
                try {
                    $sheet   = $spreadsheet->getSheetByName($candidate['name']);
                    $dayData = $this->parseSheet($sheet, $candidate['dateInfo'], $candidate['name']);
                    if ($dayData) {
                        $dailyData[]      = $dayData;
                        $parsedDays[$day] = true;
                        $totalParsed++;
                        $log[] = "Rescued mislabelled sheet: {$candidate['name']} → treated as day {$day}";
                    }
                } catch (\Exception $e) {
                    $totalFailed++;
                    $log[] = "Rescue failed for {$candidate['name']}: {$e->getMessage()}";
                }
            }

            return [
                'success'      => true,
                'daily_data'   => $dailyData,
                'total_parsed' => $totalParsed,
                'total_failed' => $totalFailed,
                'log'          => $log,
            ];

        } catch (\Exception $e) {
            return [
                'success'    => false,
                'error'      => $e->getMessage(),
                'daily_data' => [],
            ];
        }
    }

    /**
     * When the file name alone doesn't reveal the month/year, scan all sheet names
     * and use majority-vote to determine the dominant month (and most common year).
     * This handles edge cases where the file name has no recognisable month string.
     */
    private function inferDominantMonthFromSheets(array $sheetNames): ?array
    {
        $monthVotes = [];
        $yearVotes  = [];

        foreach ($sheetNames as $name) {
            $info = $this->parseSheetNameToDate($name); // no filter — just extract what's there
            if (!$info) continue;
            $monthVotes[$info['month']] = ($monthVotes[$info['month']] ?? 0) + 1;
            if ($info['year']) {
                $yearVotes[$info['year']] = ($yearVotes[$info['year']] ?? 0) + 1;
            }
        }

        if (empty($monthVotes)) return null;

        arsort($monthVotes);
        $dominantMonth = (int) array_key_first($monthVotes);

        $dominantYear = null;
        if (!empty($yearVotes)) {
            arsort($yearVotes);
            $dominantYear = (int) array_key_first($yearVotes);
        }

        return ['month' => $dominantMonth, 'year' => $dominantYear];
    }

    /**
     * Extract authoritative month and year from the file name.
     * Handles: "JANUARI 2026.xls", "GUEST November 2025.xls", "DESEMBER 2025.xls", etc.
     * MONTH_MAP is ordered longest-first so we match "SEPTEMBER" before "SEPT"/"SEP".
     */
    private function parseMonthYearFromFileName(string $fileName): ?array
    {
        $upper = strtoupper($fileName);

        // Extract 4-digit year (must be present — 2-digit years in file names are ambiguous)
        if (!preg_match('/\b(\d{4})\b/', $upper, $yearMatch)) {
            return null;
        }
        $year = (int) $yearMatch[1];
        // Sanity-check: year must be realistic for hotel data
        if ($year < 2000 || $year > 2100) {
            return null;
        }

        // Match month name — MONTH_MAP keys are ordered longest-first (see comment on const)
        foreach (self::MONTH_MAP as $name => $num) {
            // Use word-boundary-aware check: the month token must not be embedded in a longer word.
            // We look for the name surrounded by non-letter characters or string boundaries.
            if (preg_match('/(?<![A-Z])' . preg_quote($name, '/') . '(?![A-Z])/', $upper)) {
                return ['month' => $num, 'year' => $year];
            }
        }

        return null;
    }

    /**
     * Convert a raw month string from a sheet name to its numeric value (1–12).
     *
     * Tries in order:
     *  1. Exact match in MONTH_MAP  (e.g. "OKT" → 10)
     *  2. Sheet string starts with a map key (e.g. "OKTOBER" → starts with "OKT" → 10)
     *  3. Map key starts with sheet string (e.g. "NOV" key starts with "NOV" → 11)
     *
     * Because MONTH_MAP is ordered longest-key-first, the first match that fires
     * for a given month number is always the most specific one.
     */
    private function normalizeMonthName(string $raw): ?int
    {
        $raw = strtoupper(trim($raw));
        if (isset(self::MONTH_MAP[$raw])) {
            return self::MONTH_MAP[$raw];
        }
        foreach (self::MONTH_MAP as $key => $num) {
            if (str_starts_with($raw, $key) || str_starts_with($key, $raw)) {
                return $num;
            }
        }
        return null;
    }

    /**
     * Parse Excel file and extract daily occupancy data
     */
    public function parse(string $filePath, TrainingUpload $upload): array
    {
        $upload->addLog("Starting to parse file: {$upload->original_name}");

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheetNames = $spreadsheet->getSheetNames();

            $upload->addLog("Found " . count($sheetNames) . " sheets");

            // 1. Authoritative month/year from original file name
            $fileMonthYear = $this->parseMonthYearFromFileName($upload->original_name);

            // 2. Fallback: infer from dominant month across all sheet tabs
            if (!$fileMonthYear) {
                $fileMonthYear = $this->inferDominantMonthFromSheets($sheetNames);
                if ($fileMonthYear) {
                    $upload->addLog("File period inferred from sheets: month={$fileMonthYear['month']} year={$fileMonthYear['year']}");
                }
            } else {
                $upload->addLog("File period from name: month={$fileMonthYear['month']} year={$fileMonthYear['year']}");
            }

            $dailyData   = [];
            $parsedDays  = [];
            $straySheets = [];
            $errors      = [];
            $totalParsed = 0;
            $totalFailed = 0;

            foreach ($sheetNames as $sheetName) {
                // Skip non-date sheets, enforce month/year from file name
                $dateInfo = $this->parseSheetNameToDate($sheetName, $fileMonthYear);
                if (!$dateInfo) {
                    // Check if it's a rescuable stray (parseable but wrong month label)
                    $rawInfo = $this->parseSheetNameToDate($sheetName);
                    if ($rawInfo && $fileMonthYear && $rawInfo['month'] !== $fileMonthYear['month']) {
                        $straySheets[$rawInfo['day']] = [
                            'name'     => $sheetName,
                            'dateInfo' => [
                                'day'   => $rawInfo['day'],
                                'month' => $fileMonthYear['month'],
                                'year'  => $fileMonthYear['year'] ?? $rawInfo['year'] ?? (int) date('Y'),
                            ],
                        ];
                        $upload->addLog("Stray sheet (wrong month label): {$sheetName} — queued for rescue", 'warning');
                    } else {
                        $upload->addLog("Skipping sheet: {$sheetName} (not a date sheet)", 'warning');
                    }
                    continue;
                }

                try {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    $dayData = $this->parseSheet($sheet, $dateInfo, $sheetName);

                    if ($dayData) {
                        $dailyData[]                  = $dayData;
                        $parsedDays[$dateInfo['day']] = true;
                        $totalParsed++;
                        $upload->addLog("Parsed sheet: {$sheetName} - {$dayData['total_occupied']} rooms occupied");
                    }
                } catch (\Exception $e) {
                    $totalFailed++;
                    $errors[] = ['sheet' => $sheetName, 'error' => $e->getMessage()];
                    $upload->addLog("Failed to parse sheet: {$sheetName} - {$e->getMessage()}", 'error');
                }
            }

            // Rescue pass: stray mislabelled sheets whose day is not yet covered.
            // Only rescue if the day is valid for this month (e.g. no day 31 in November).
            $daysInMonth = $fileMonthYear
                ? cal_days_in_month(CAL_GREGORIAN, $fileMonthYear['month'], $fileMonthYear['year'] ?? (int) date('Y'))
                : 31;

            foreach ($straySheets as $day => $candidate) {
                if ($day > $daysInMonth) {
                    $upload->addLog("Rescue skipped (day {$day} impossible for this month): {$candidate['name']}", 'warning');
                    continue;
                }
                if (isset($parsedDays[$day])) {
                    continue;
                }
                try {
                    $sheet   = $spreadsheet->getSheetByName($candidate['name']);
                    $dayData = $this->parseSheet($sheet, $candidate['dateInfo'], $candidate['name']);
                    if ($dayData) {
                        $dailyData[]      = $dayData;
                        $parsedDays[$day] = true;
                        $totalParsed++;
                        $upload->addLog("Rescued mislabelled sheet: {$candidate['name']} → day {$day}");
                    }
                } catch (\Exception $e) {
                    $totalFailed++;
                    $errors[] = ['sheet' => $candidate['name'], 'error' => $e->getMessage()];
                    $upload->addLog("Rescue failed for {$candidate['name']}: {$e->getMessage()}", 'error');
                }
            }
            
            // Update upload stats
            $upload->records_parsed = $totalParsed;
            $upload->records_failed = $totalFailed;
            if (!empty($errors)) {
                $upload->error_details = $errors;
            }
            
            // Extract month/year from parsed data
            if (!empty($dailyData)) {
                $firstDate = Carbon::parse($dailyData[0]['date']);
                $upload->month_period = $firstDate->month;
                $upload->year_period = $firstDate->year;
            }
            
            $upload->save();
            $upload->addLog("Parsing complete: {$totalParsed} days parsed, {$totalFailed} failed");
            
            return [
                'success' => true,
                'daily_data' => $dailyData,
                'total_parsed' => $totalParsed,
                'total_failed' => $totalFailed,
                'errors' => $errors,
            ];
            
        } catch (\Exception $e) {
            Log::error('Excel parsing failed', [
                'file' => $filePath,
                'error' => $e->getMessage(),
            ]);
            
            $upload->addLog("Fatal parsing error: {$e->getMessage()}", 'error');
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'daily_data' => [],
            ];
        }
    }

    /**
     * Parse sheet name to extract date.
     *
     * Handles all real-world variations found in the guest files:
     *   "01 DES 25"      "05 DES 2025"   " 11 DES 25"   "24 DES 25 "
     *   "05 OKT2025"     "22 OKT25"      "31OKT 25"     "21 OKTOBER 25"
     *
     * If $fileMonthYear is provided, the sheet's month must match (wrong-month stray
     * sheets like "28 SEPT 25" inside a November file are skipped), and the file's
     * authoritative year always overrides whatever year is in the sheet tab.
     */
    private function parseSheetNameToDate(string $sheetName, ?array $fileMonthYear = null): ?array
    {
        // Normalise: upper-case, collapse multiple spaces
        $sheetName = trim(strtoupper($sheetName));
        $sheetName = preg_replace('/\s+/', ' ', $sheetName);

        // Skip obvious non-date sheets
        $skipPatterns = ['SHEET', 'SUMMARY', 'TOTAL', 'REKAP'];
        foreach ($skipPatterns as $pattern) {
            if (str_contains($sheetName, $pattern)) {
                return null;
            }
        }

        // Flexible pattern — spaces between the three parts are all optional
        // Captures: (day 1-2 digits) + optional space + (month letters) + optional space + (year 2 or 4 digits, optional)
        $pattern = '/^(\d{1,2})\s*([A-Z]+)\s*(\d{2,4})?\s*$/';

        if (!preg_match($pattern, $sheetName, $matches)) {
            return null;
        }

        $day      = (int) $matches[1];
        $monthStr = trim($matches[2]);
        $yearStr  = isset($matches[3]) ? trim($matches[3]) : null;

        // Validate day range early
        if ($day < 1 || $day > 31) {
            return null;
        }

        // Convert month string to number using dedicated normalizer
        $month = $this->normalizeMonthName($monthStr);
        if (!$month) {
            return null;
        }

        // If we know the file's authoritative month, skip sheets from a different month
        // (stray tabs like "28 SEPT 25" inside a November file)
        if ($fileMonthYear && $month !== $fileMonthYear['month']) {
            return null;
        }

        // Determine year:
        // 1. If file name gave us an authoritative year → always use that (fixes "10 JAN 2025" inside JANUARI 2026.xls)
        // 2. Otherwise fall back to the year in the sheet tab
        // 3. Final fallback: current year
        if ($fileMonthYear) {
            $year = $fileMonthYear['year'];
        } elseif ($yearStr) {
            if (strlen($yearStr) === 2) {
                $y = (int) $yearStr;
                $year = $y < 50 ? 2000 + $y : 1900 + $y;
            } else {
                $year = (int) $yearStr;
            }
        } else {
            $year = (int) date('Y');
        }

        return [
            'day'   => $day,
            'month' => $month,
            'year'  => $year,
        ];
    }

    /**
     * Parse a single sheet to extract room occupancy data
     */
    private function parseSheet($sheet, array $dateInfo, string $sheetName): ?array
    {
        $data = $sheet->toArray(null, true, true, false);
        
        // Find header row (contains "RM" or "ROOM")
        $headerRow = null;
        $dataStartRow = 6; // Default
        
        for ($i = 0; $i < min(10, count($data)); $i++) {
            $row = $data[$i] ?? [];
            if (isset($row[0]) && in_array(strtoupper(trim((string)$row[0])), ['RM', 'ROOM', 'NO'])) {
                $headerRow = $i;
                $dataStartRow = $i + 1;
                break;
            }
        }
        
        // Extract room data
        $roomsByType = [];
        $totalOccupied = 0;
        $totalRevenue = 0;
        $totalPersons = 0;
        
        // Initialize room types
        foreach (['STD', 'SPR', 'JS', 'FMY'] as $type) {
            $roomsByType[$type] = [
                'total' => 0,
                'occupied' => 0,
                'revenue' => 0,
            ];
        }
        
        // Process data rows
        for ($i = $dataStartRow; $i < count($data); $i++) {
            $row = $data[$i];
            
            // Check if this is a room row (room number should be 100-400)
            $roomNum = $row[self::COL_ROOM] ?? null;

            // Check if this is summary row (more reliable detection)
            $firstCell = strtoupper(trim((string)($row[self::COL_ROOM] ?? '')));
            if (in_array($firstCell, ['TOTAL', 'SUMMARY', 'JUMLAH', 'SUM', 'GRAND TOTAL'])) {
                // Reached summary row - stop parsing
                break;
            }

            if (!is_numeric($roomNum) || $roomNum < 100 || $roomNum > 400) {
                continue;
            }
            
            // Get room type
            $typeRaw = trim((string)($row[self::COL_TYPE] ?? ''));
            $typeCode = $this->normalizeRoomType($typeRaw);
            
            if (!$typeCode || $typeCode === 'TYPE') {
                continue;
            }
            
            // Count room
            $roomsByType[$typeCode]['total']++;
            
            // Check if occupied (OC = 1)
            $occupied = $row[self::COL_OCCUPIED] ?? null;
            if ($occupied == 1) {
                $roomsByType[$typeCode]['occupied']++;
                $totalOccupied++;

                // Get price with validation — handle "Rp 180,000" string format
                $rawPrice = $row[self::COL_PRICE] ?? 0;
                $price = $this->parsePrice($rawPrice);
                if ($price < 0) {
                    Log::warning("Negative price detected in sheet {$sheetName}, room {$roomNum}: {$price} - setting to 0");
                    $price = 0;
                }
                $roomsByType[$typeCode]['revenue'] += $price;
                $totalRevenue += $price;

                // Get persons with validation
                $persons = $row[self::COL_PERSONS] ?? 0;
                if (is_numeric($persons)) {
                    $persons = (int) $persons;
                    // Validate persons is positive
                    if ($persons < 0) {
                        Log::warning("Negative persons count in sheet {$sheetName}, room {$roomNum}: {$persons} - setting to 0");
                        $persons = 0;
                    }
                    $totalPersons += $persons;
                }
            }
        }
        
        // Build date string
        $year = $dateInfo['year'] ?? date('Y');
        $dateString = sprintf('%04d-%02d-%02d', $year, $dateInfo['month'], $dateInfo['day']);
        
        // Calculate occupancy rates
        // Always include all 4 room types, even if count is 0
        $roomBreakdown = [];
        foreach ($roomsByType as $type => $stats) {
            $roomBreakdown[$type] = [
                'rooms_available' => $stats['total'],
                'rooms_occupied' => $stats['occupied'],
                'occupancy_rate' => $stats['total'] > 0
                    ? round(($stats['occupied'] / $stats['total']) * 100, 2)
                    : 0,
                'revenue' => $stats['revenue'],
            ];
        }
        
        return [
            'date' => $dateString,
            'sheet_name' => $sheetName,
            'total_occupied' => $totalOccupied,
            'total_revenue' => $totalRevenue,
            'total_persons' => $totalPersons,
            'room_breakdown' => $roomBreakdown,
        ];
    }

    /**
     * Parse price from various Excel formats:
     * - Numeric: 180000
     * - String: " Rp 180,000 ", "Rp180.000", "180,000"
     */
    private function parsePrice($raw): float
    {
        if ($raw === null || $raw === '') return 0.0;
        if (is_numeric($raw)) return (float) $raw;

        // Strip currency symbol, spaces, thousands separators
        $cleaned = preg_replace('/[Rp\s\.]/i', '', (string) $raw);
        $cleaned = str_replace(',', '', $cleaned); // remove comma thousands separator
        $cleaned = trim($cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : 0.0;
    }

    /**
     * Normalize room type from Excel variations to system code
     */
    private function normalizeRoomType(string $rawType): ?string
    {
        $rawType = strtoupper(trim($rawType));
        
        // Direct mapping
        if (isset(self::ROOM_TYPE_MAP[$rawType])) {
            return self::ROOM_TYPE_MAP[$rawType];
        }
        
        // Fuzzy matching
        foreach (self::ROOM_TYPE_MAP as $pattern => $code) {
            if (str_contains($rawType, $pattern) || str_contains($pattern, $rawType)) {
                return $code;
            }
        }
        
        // Try to detect type from keywords
        if (str_contains($rawType, 'STD') || str_contains($rawType, 'STANDARD')) {
            return 'STD';
        }
        if (str_contains($rawType, 'SUP') || str_contains($rawType, 'SUPERIOR')) {
            return 'SPR';
        }
        if (str_contains($rawType, 'SUITE') || str_contains($rawType, 'JS')) {
            return 'JS';
        }
        if (str_contains($rawType, 'FAMILY') || str_contains($rawType, 'FMY')) {
            return 'FMY';
        }
        
        return null;
    }

    /**
     * Validate parsed data for consistency
     */
    public function validateParsedData(array $dailyData): array
    {
        $errors = [];
        $warnings = [];
        
        foreach ($dailyData as $day) {
            $date = $day['date'];
            
            // Check for zero occupancy
            if ($day['total_occupied'] === 0) {
                $warnings[] = "Zero occupancy on {$date} - verify if correct";
            }
            
            // Check for unusually high occupancy
            $totalRooms = 0;
            foreach ($day['room_breakdown'] as $breakdown) {
                $totalRooms += $breakdown['rooms_available'];
            }
            
            if ($totalRooms > 0 && ($day['total_occupied'] / $totalRooms) > 1) {
                $errors[] = "Invalid occupancy on {$date}: more occupied than available";
            }
            
            // Check for missing room types
            $expectedTypes = ['STD', 'SPR', 'JS', 'FMY'];
            $foundTypes = array_keys($day['room_breakdown']);
            $missingTypes = array_diff($expectedTypes, $foundTypes);
            
            if (!empty($missingTypes)) {
                $warnings[] = "Missing room types on {$date}: " . implode(', ', $missingTypes);
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }
}
