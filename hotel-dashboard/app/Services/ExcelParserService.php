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
     * Month name mapping (Indonesian)
     */
    private const MONTH_MAP = [
        'JANUARI' => 1, 'JAN' => 1,
        'FEBRUARI' => 2, 'FEB' => 2,
        'MARET' => 3, 'MAR' => 3,
        'APRIL' => 4, 'APR' => 4,
        'MEI' => 5, 'MAY' => 5,
        'JUNI' => 6, 'JUN' => 6,
        'JULI' => 7, 'JUL' => 7,
        'AGUSTUS' => 8, 'AGUS' => 8, 'AGU' => 8, 'AUG' => 8,
        'SEPTEMBER' => 9, 'SEP' => 9, 'SEPT' => 9,
        'OKTOBER' => 10, 'OKT' => 10, 'OCT' => 10,
        'NOVEMBER' => 11, 'NOV' => 11,
        'DESEMBER' => 12, 'DES' => 12, 'DEC' => 12,
    ];

    /**
     * Column indices in the Excel file
     */
    private const COL_ROOM = 0;      // RM - Room number
    private const COL_OCCUPIED = 1;  // OC - Occupied flag
    private const COL_PERSONS = 2;   // P - Number of persons
    private const COL_PRICE = 11;    // PRICE
    private const COL_TYPE = 15;     // TYPE

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
            
            $dailyData = [];
            $errors = [];
            $totalParsed = 0;
            $totalFailed = 0;
            
            foreach ($sheetNames as $sheetName) {
                // Skip non-date sheets
                $dateInfo = $this->parseSheetNameToDate($sheetName);
                if (!$dateInfo) {
                    $upload->addLog("Skipping sheet: {$sheetName} (not a date sheet)", 'warning');
                    continue;
                }
                
                try {
                    $sheet = $spreadsheet->getSheetByName($sheetName);
                    $dayData = $this->parseSheet($sheet, $dateInfo, $sheetName);
                    
                    if ($dayData) {
                        $dailyData[] = $dayData;
                        $totalParsed++;
                        $upload->addLog("Parsed sheet: {$sheetName} - {$dayData['total_occupied']} rooms occupied");
                    }
                } catch (\Exception $e) {
                    $totalFailed++;
                    $errors[] = [
                        'sheet' => $sheetName,
                        'error' => $e->getMessage(),
                    ];
                    $upload->addLog("Failed to parse sheet: {$sheetName} - {$e->getMessage()}", 'error');
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
     * Parse sheet name to extract date
     * Handles various formats: "01 JUNI 2025", "02 JUN 25", "03 JUNI 25"
     */
    private function parseSheetNameToDate(string $sheetName): ?array
    {
        // Clean up sheet name
        $sheetName = trim(strtoupper($sheetName));
        
        // Skip non-date sheets
        $skipPatterns = ['SHEET', 'SUMMARY', 'TOTAL', 'REKAP'];
        foreach ($skipPatterns as $pattern) {
            if (str_contains($sheetName, $pattern)) {
                return null;
            }
        }
        
        // Pattern: DD MONTH YYYY or DD MONTH YY
        // Examples: "01 JUNI 2025", "02 JUN 25", "03 AGUS 25"
        $pattern = '/(\d{1,2})\s+([A-Z]+)\s*(\d{2,4})?/';
        
        if (preg_match($pattern, $sheetName, $matches)) {
            $day = (int) $matches[1];
            $monthStr = $matches[2];
            $yearStr = $matches[3] ?? null;
            
            // Convert month name to number
            $month = self::MONTH_MAP[$monthStr] ?? null;
            if (!$month) {
                return null;
            }
            
            // Convert year
            $year = null;
            if ($yearStr) {
                $year = strlen($yearStr) === 2 ? 2000 + (int) $yearStr : (int) $yearStr;
            }
            
            // Validate day
            if ($day < 1 || $day > 31) {
                return null;
            }
            
            return [
                'day' => $day,
                'month' => $month,
                'year' => $year,
            ];
        }
        
        return null;
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
            if (!is_numeric($roomNum) || $roomNum < 100 || $roomNum > 400) {
                // Check if this is summary row (contains total)
                if (is_numeric($row[self::COL_OCCUPIED] ?? null) && ($row[self::COL_OCCUPIED] ?? 0) > 10) {
                    // This might be summary row - extract totals for validation
                    break;
                }
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
                
                // Get price
                $price = $row[self::COL_PRICE] ?? 0;
                if (is_numeric($price)) {
                    $roomsByType[$typeCode]['revenue'] += (float) $price;
                    $totalRevenue += (float) $price;
                }
                
                // Get persons
                $persons = $row[self::COL_PERSONS] ?? 0;
                if (is_numeric($persons)) {
                    $totalPersons += (int) $persons;
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
