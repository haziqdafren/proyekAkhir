<?php

namespace Database\Seeders;

use App\Models\HistoricalOccupancyData;
use App\Models\RoomType;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds historical occupancy data from the original training dataset.
 *
 * Data source: 2021_2025_Clean1.xlsx (58 months, Jan 2021 – Oct 2025)
 * Values reverse-engineered from MinMaxScaler using notebook anchor points:
 *   KT:  min=360, max=1296  | STD: min=234, max=803
 *   SPR: min=110, max=436   | FMY: min=11,  max=76  | JS: min=3, max=36
 *
 * Daily records are created by distributing monthly totals evenly across all
 * days in the month — the same way the upload parser works for new months.
 */
class HistoricalOccupancySeeder extends Seeder
{
    /**
     * Monthly totals reconstructed from 2021_2025_Clean1.xlsx.
     * Format: 'YYYY-MM' => [kt, std, spr, fmy, js]
     */
    private const MONTHLY_DATA = [
        '2021-01' => ['kt'=>974,  'std'=>552, 'spr'=>333, 'fmy'=>62, 'js'=>27],
        '2021-02' => ['kt'=>883,  'std'=>510, 'spr'=>292, 'fmy'=>59, 'js'=>22],
        '2021-03' => ['kt'=>1022, 'std'=>578, 'spr'=>367, 'fmy'=>58, 'js'=>19],
        '2021-04' => ['kt'=>917,  'std'=>557, 'spr'=>287, 'fmy'=>46, 'js'=>27],
        '2021-05' => ['kt'=>517,  'std'=>312, 'spr'=>154, 'fmy'=>39, 'js'=>11],
        '2021-06' => ['kt'=>959,  'std'=>530, 'spr'=>335, 'fmy'=>68, 'js'=>26],
        '2021-07' => ['kt'=>585,  'std'=>321, 'spr'=>211, 'fmy'=>33, 'js'=>20],
        '2021-08' => ['kt'=>360,  'std'=>234, 'spr'=>110, 'fmy'=>13, 'js'=>3],
        '2021-09' => ['kt'=>726,  'std'=>450, 'spr'=>225, 'fmy'=>43, 'js'=>8],
        '2021-10' => ['kt'=>1132, 'std'=>653, 'spr'=>372, 'fmy'=>76, 'js'=>30],
        '2021-11' => ['kt'=>887,  'std'=>522, 'spr'=>300, 'fmy'=>51, 'js'=>14],
        '2021-12' => ['kt'=>988,  'std'=>492, 'spr'=>407, 'fmy'=>61, 'js'=>28],
        '2022-01' => ['kt'=>885,  'std'=>544, 'spr'=>284, 'fmy'=>37, 'js'=>20],
        '2022-02' => ['kt'=>600,  'std'=>391, 'spr'=>173, 'fmy'=>25, 'js'=>11],
        '2022-03' => ['kt'=>908,  'std'=>560, 'spr'=>291, 'fmy'=>37, 'js'=>20],
        '2022-04' => ['kt'=>705,  'std'=>455, 'spr'=>204, 'fmy'=>31, 'js'=>15],
        '2022-05' => ['kt'=>1239, 'std'=>784, 'spr'=>374, 'fmy'=>53, 'js'=>28],
        '2022-06' => ['kt'=>1056, 'std'=>681, 'spr'=>318, 'fmy'=>39, 'js'=>18],
        '2022-07' => ['kt'=>1106, 'std'=>634, 'spr'=>408, 'fmy'=>43, 'js'=>21],
        '2022-08' => ['kt'=>911,  'std'=>563, 'spr'=>306, 'fmy'=>26, 'js'=>14],
        '2022-09' => ['kt'=>545,  'std'=>293, 'spr'=>220, 'fmy'=>16, 'js'=>16],
        '2022-10' => ['kt'=>659,  'std'=>344, 'spr'=>264, 'fmy'=>32, 'js'=>19],
        '2022-11' => ['kt'=>756,  'std'=>386, 'spr'=>307, 'fmy'=>41, 'js'=>22],
        '2022-12' => ['kt'=>1130, 'std'=>614, 'spr'=>428, 'fmy'=>52, 'js'=>36],
        '2023-01' => ['kt'=>826,  'std'=>497, 'spr'=>267, 'fmy'=>43, 'js'=>19],
        '2023-02' => ['kt'=>628,  'std'=>393, 'spr'=>210, 'fmy'=>11, 'js'=>14],
        '2023-03' => ['kt'=>642,  'std'=>386, 'spr'=>220, 'fmy'=>22, 'js'=>14],
        '2023-04' => ['kt'=>742,  'std'=>389, 'spr'=>279, 'fmy'=>51, 'js'=>22],
        '2023-05' => ['kt'=>707,  'std'=>391, 'spr'=>274, 'fmy'=>19, 'js'=>23],
        '2023-06' => ['kt'=>756,  'std'=>412, 'spr'=>288, 'fmy'=>31, 'js'=>25],
        '2023-07' => ['kt'=>901,  'std'=>535, 'spr'=>311, 'fmy'=>35, 'js'=>14],
        '2023-08' => ['kt'=>640,  'std'=>358, 'spr'=>239, 'fmy'=>21, 'js'=>20],
        '2023-09' => ['kt'=>772,  'std'=>426, 'spr'=>297, 'fmy'=>28, 'js'=>20],
        '2023-10' => ['kt'=>807,  'std'=>448, 'spr'=>289, 'fmy'=>50, 'js'=>20],
        '2023-11' => ['kt'=>933,  'std'=>528, 'spr'=>334, 'fmy'=>52, 'js'=>18],
        '2023-12' => ['kt'=>1026, 'std'=>567, 'spr'=>361, 'fmy'=>66, 'js'=>32],
        '2024-01' => ['kt'=>726,  'std'=>400, 'spr'=>255, 'fmy'=>51, 'js'=>20],
        '2024-02' => ['kt'=>808,  'std'=>443, 'spr'=>302, 'fmy'=>43, 'js'=>19],
        '2024-03' => ['kt'=>1018, 'std'=>559, 'spr'=>387, 'fmy'=>48, 'js'=>22],
        '2024-04' => ['kt'=>933,  'std'=>525, 'spr'=>334, 'fmy'=>47, 'js'=>27],
        '2024-05' => ['kt'=>1085, 'std'=>584, 'spr'=>436, 'fmy'=>43, 'js'=>22],
        '2024-06' => ['kt'=>1009, 'std'=>612, 'spr'=>328, 'fmy'=>47, 'js'=>22],
        '2024-07' => ['kt'=>1268, 'std'=>799, 'spr'=>404, 'fmy'=>35, 'js'=>30],
        '2024-08' => ['kt'=>1073, 'std'=>621, 'spr'=>396, 'fmy'=>25, 'js'=>31],
        '2024-09' => ['kt'=>946,  'std'=>554, 'spr'=>336, 'fmy'=>33, 'js'=>23],
        '2024-10' => ['kt'=>1008, 'std'=>633, 'spr'=>316, 'fmy'=>39, 'js'=>18],
        '2024-11' => ['kt'=>966,  'std'=>597, 'spr'=>325, 'fmy'=>33, 'js'=>11],
        '2024-12' => ['kt'=>1296, 'std'=>803, 'spr'=>412, 'fmy'=>47, 'js'=>33],
        '2025-01' => ['kt'=>1004, 'std'=>636, 'spr'=>295, 'fmy'=>53, 'js'=>20],
        '2025-02' => ['kt'=>854,  'std'=>563, 'spr'=>231, 'fmy'=>38, 'js'=>19],
        '2025-03' => ['kt'=>615,  'std'=>382, 'spr'=>195, 'fmy'=>26, 'js'=>12],
        '2025-04' => ['kt'=>889,  'std'=>562, 'spr'=>279, 'fmy'=>32, 'js'=>16],
        '2025-05' => ['kt'=>999,  'std'=>634, 'spr'=>314, 'fmy'=>33, 'js'=>18],
        '2025-06' => ['kt'=>884,  'std'=>573, 'spr'=>256, 'fmy'=>37, 'js'=>15],
        '2025-07' => ['kt'=>950,  'std'=>595, 'spr'=>289, 'fmy'=>36, 'js'=>30],
        '2025-08' => ['kt'=>706,  'std'=>454, 'spr'=>219, 'fmy'=>17, 'js'=>15],
        '2025-09' => ['kt'=>815,  'std'=>547, 'spr'=>222, 'fmy'=>30, 'js'=>16],
        '2025-10' => ['kt'=>580,  'std'=>371, 'spr'=>175, 'fmy'=>19, 'js'=>13],
    ];

    // Room capacities matching notebook TOTAL_ROOMS=58
    private const ROOM_CAPACITY = ['STD' => 32, 'SPR' => 19, 'FMY' => 3, 'JS' => 2];

    private const TYPE_KEY = ['STD' => 'std', 'SPR' => 'spr', 'FMY' => 'fmy', 'JS' => 'js'];

    public function run(): void
    {
        $this->command->info('Replacing fake seeded data with real data from 2021_2025_Clean1.xlsx...');

        // Delete only the pre-upload historical data — keep real uploads (Nov 2025 onward)
        $deleted = HistoricalOccupancyData::where('date', '<=', '2025-10-31')->delete();
        $this->command->info("Deleted {$deleted} fake records.");

        $roomTypes = RoomType::where('is_active', true)->pluck('id', 'code');
        $rows      = [];
        $now       = now();

        foreach (self::MONTHLY_DATA as $month => $data) {
            [$year, $mon] = explode('-', $month);
            $daysInMonth  = cal_days_in_month(CAL_GREGORIAN, (int) $mon, (int) $year);

            foreach (['STD', 'SPR', 'FMY', 'JS'] as $code) {
                $roomTypeId = $roomTypes[$code] ?? null;
                if (!$roomTypeId) continue;

                $monthlyTotal = $data[self::TYPE_KEY[$code]];
                $capacity     = self::ROOM_CAPACITY[$code];

                // Distribute monthly total evenly across days (same logic as upload parser)
                $basePerDay = intdiv($monthlyTotal, $daysInMonth);
                $remainder  = $monthlyTotal % $daysInMonth;

                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $occupied = $basePerDay + ($day <= $remainder ? 1 : 0);
                    $occupied = min($occupied, $capacity);

                    $occRate = $capacity > 0
                        ? round(($occupied / $capacity) * 100, 2)
                        : 0.0;

                    $rows[] = [
                        'room_type_id'    => $roomTypeId,
                        'date'            => sprintf('%04d-%02d-%02d', (int) $year, (int) $mon, $day),
                        'rooms_occupied'  => $occupied,
                        'rooms_available' => $capacity,
                        'occupancy_rate'  => $occRate,
                        'revenue'         => 0,
                        'created_at'      => $now,
                        'updated_at'      => $now,
                    ];
                }
            }

            if (count($rows) >= 500) {
                DB::table('historical_occupancy_data')->insert($rows);
                $rows = [];
            }
        }

        if (!empty($rows)) {
            DB::table('historical_occupancy_data')->insert($rows);
        }

        $inserted = HistoricalOccupancyData::where('date', '<=', '2025-10-31')->count();
        $this->command->info("Done. Inserted {$inserted} daily records for 58 months (Jan 2021 – Oct 2025).");
    }
}
