<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Models\RoomType;
use App\Services\MLPredictionService;
use App\Services\FeatureEngineeringService;
use App\Services\HistoricalDataAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateMonthlyPredictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'predictions:generate
                            {--months=3 : Number of months ahead to predict (1-3)}
                            {--clear : Clear existing predictions before generating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly occupancy predictions using LSTM models (1-3 months ahead)';

    protected MLPredictionService $mlService;
    protected FeatureEngineeringService $featureService;
    protected HistoricalDataAggregationService $aggregationService;

    public function __construct(
        MLPredictionService $mlService,
        FeatureEngineeringService $featureService,
        HistoricalDataAggregationService $aggregationService
    ) {
        parent::__construct();
        $this->mlService = $mlService;
        $this->featureService = $featureService;
        $this->aggregationService = $aggregationService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🤖 Generating Monthly LSTM Predictions...');
        $this->newLine();

        // Get options
        $monthsAhead = (int) $this->option('months');
        $monthsAhead = max(1, min($monthsAhead, 3)); // Limit to 1-3 months

        if ($this->option('clear')) {
            $this->warn('Clearing existing predictions...');
            Prediction::where('model_type', 'multi')->delete();
        }

        try {
            // Step 1: Get last 12 months of aggregated historical data
            $this->info('📊 Step 1: Aggregating historical data...');

            // Use ACTUAL last historical date from database (not Carbon::now())
            $lastHistoricalDate = \App\Models\HistoricalOccupancyData::max('date');
            if (!$lastHistoricalDate) {
                $this->error('❌ No historical data found in database');
                return Command::FAILURE;
            }

            $endDate = Carbon::parse($lastHistoricalDate)->startOfMonth(); // Last month with data
            $startDate = $endDate->copy()->subMonths(11); // 12 months total

            $this->info("   Using historical data up to: {$endDate->format('Y-m')}");
            $this->info("   (Last historical record: {$lastHistoricalDate})");

            $aggregatedData = $this->aggregationService->getAggregatedMonthlyData($startDate, $endDate);

            $this->info("   Found {$aggregatedData->count()} months of data ({$startDate->format('Y-m')} to {$endDate->format('Y-m')})");

            if ($aggregatedData->count() < 6) {
                $this->error("❌ Insufficient historical data. Need at least 6 months, found: {$aggregatedData->count()}");
                return Command::FAILURE;
            }

            // Get room capacities
            $roomCapacities = $this->mlService->getRoomCapacities();

            // Use the last historical month as the base for predictions
            $lastHistoricalMonth = $endDate;
            $this->info("   Predicting from last historical month: {$lastHistoricalMonth->format('Y-m')}");
            $this->newLine();

            // Step 2-4: Generate separate prediction for each target month
            DB::beginTransaction();

            $storedCount = 0;
            $firstResult = null;

            for ($i = 1; $i <= $monthsAhead; $i++) {
                $predictForMonth = $lastHistoricalMonth->copy()->addMonths($i)->startOfMonth();

                $this->info("Month +{$i}: {$predictForMonth->format('F Y')}");

                // Step 2: Project historical data forward for THIS specific target month
                $this->info('   📈 Projecting data forward to ' . $predictForMonth->format('M Y') . '...');
                $projectedData = $this->projectHistoricalDataForward($aggregatedData, $predictForMonth);

                // Step 3: Prepare features for THIS specific target month using projected data
                $this->info('   🔧 Engineering features for this month...');
                $features = $this->featureService->prepareFeatures($projectedData);

                if (!$this->featureService->validateFeatureArray($features)) {
                    $this->error('   ❌ Feature validation failed. Expected shape: [6, 15]');
                    DB::rollBack();
                    return Command::FAILURE;
                }

                if ($i === 1) {
                    $this->displayFeatureSample($features);
                    $this->info('   Room capacities: ' . json_encode($roomCapacities));
                }

                // Step 4: Make prediction for THIS month
                $this->info('   🧠 Running LSTM multi-output model...');
                $result = $this->mlService->predictMultiOutput($features, $roomCapacities);

                if (!$result['success']) {
                    $this->error('   ❌ Prediction failed');
                    DB::rollBack();
                    return Command::FAILURE;
                }

                if ($firstResult === null) {
                    $firstResult = $result;
                    $this->info("   Model version: {$result['model_version']}");
                    $this->info("   Overall MAPE: " . round($result['overall_mape'], 2) . '%');
                }

                // Step 5: Store this month's predictions for each room type
                foreach ($result['predictions'] as $roomCode => $prediction) {
                    $roomType = RoomType::where('code', $roomCode)->first();

                    if (!$roomType) {
                        $this->warn("   ⚠️  Room type '{$roomCode}' not found in database");
                        continue;
                    }

                    $daysInMonth = $predictForMonth->daysInMonth;
                    $predictionRecord = Prediction::create([
                        'room_type_id' => $roomType->id,
                        'prediction_date' => Carbon::now(),
                        'predicted_for_date' => $predictForMonth,
                        'predicted_occupancy_rate' => $prediction['occupancy_rate'],
                        'predicted_rooms_occupied' => (int) $prediction['rooms_sold'],
                        'predicted_revenue' => round($prediction['rooms_sold'] * $roomType->base_price * $daysInMonth),
                        'confidence_level' => 100 - $prediction['mape'],
                        'model_type' => 'multi',
                        'model_version' => $result['model_version'],
                    ]);

                    $this->line("   ✓ {$roomType->name}: {$prediction['occupancy_rate']}% ({$prediction['rooms_sold']} rooms) - Confidence: " . round(100 - $prediction['mape'], 1) . '%');
                    $storedCount++;
                }

                $this->newLine();
            }

            DB::commit();

            // Success summary
            $this->newLine();
            $this->info("✅ Successfully generated {$storedCount} predictions!");
            $this->info("📈 Predictions cover: " . $lastHistoricalMonth->copy()->addMonth()->format('M Y') . " to " . $lastHistoricalMonth->copy()->addMonths($monthsAhead)->format('M Y'));

            // Display prediction summary
            $this->newLine();
            $this->displayPredictionSummary($result['predictions']);

            Log::info('Monthly predictions generated successfully', [
                'months_ahead' => $monthsAhead,
                'predictions_count' => $storedCount,
                'model_version' => $result['model_version'],
                'overall_mape' => $result['overall_mape'],
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Prediction generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    /**
     * Display sample of prepared features
     */
    private function displayFeatureSample(array $features): void
    {
        $this->newLine();
        $this->line('   Feature Sample (first 5 features of first month):');
        $featureNames = $this->featureService->getFeatureNames();

        for ($i = 0; $i < min(5, count($features[0])); $i++) {
            $value = round($features[0][$i], 4);
            $this->line("      - {$featureNames[$i]}: {$value}");
        }
        $this->line('      ... (10 more features)');
        $this->newLine();
    }

    /**
     * Display prediction summary table
     */
    private function displayPredictionSummary(array $predictions): void
    {
        $this->info('📊 Prediction Summary:');
        $this->table(
            ['Room Type', 'Occupancy Rate', 'Rooms Sold', 'MAPE', 'R²'],
            collect($predictions)->map(function ($pred, $code) {
                return [
                    $code,
                    round($pred['occupancy_rate'], 1) . '%',
                    round($pred['rooms_sold'], 1),
                    round($pred['mape'], 2) . '%',
                    round($pred['r2'], 3),
                ];
            })->toArray()
        );
    }

    /**
     * Project historical data forward by creating synthetic months
     * This ensures different predictions for different target months
     */
    private function projectHistoricalDataForward(\Illuminate\Support\Collection $historicalData, Carbon $targetMonth): \Illuminate\Support\Collection
    {
        // Get the last real month in historical data
        $sorted = $historicalData->sortBy('date');
        $lastRealMonth = $sorted->last();

        if (!$lastRealMonth) {
            return $sorted;
        }

        $lastDate = Carbon::parse($lastRealMonth->date);

        // Calculate how many months ahead we're predicting
        $monthsAhead = $lastDate->copy()->startOfMonth()->diffInMonths($targetMonth->copy()->startOfMonth());

        // Skip projection for immediate next month (already has enough recent data)
        // For 2+ months ahead, use synthetic projection to vary inputs
        if ($monthsAhead <= 1) {
            return $sorted;
        }

        // Create synthetic months by projecting forward based on recent trend
        $projected = $sorted->toBase();

        for ($i = 1; $i < $monthsAhead; $i++) {
            $syntheticDate = $lastDate->copy()->addMonths($i)->startOfMonth();
            $syntheticMonth = $this->createSyntheticMonth($projected, $syntheticDate);
            $projected->push($syntheticMonth);
        }

        // Return only the last 24 months (to maintain data quality for YoY calculations)
        return $projected->sortBy('date')->take(-24);
    }

    /**
     * Create a synthetic month by projecting from recent trends with seasonal adjustment
     */
    private function createSyntheticMonth(\Illuminate\Support\Collection $historicalData, Carbon $targetDate): object
    {
        // Get last 3 months for trend calculation
        $recent = $historicalData->sortBy('date')->take(-3);

        if ($recent->count() < 3) {
            $recent = $historicalData->sortBy('date')->take(-$historicalData->count());
        }

        // Calculate average values with a slight trend
        $avgOccupancy = $recent->avg('occupancy_rate') ?? 50.0;
        $avgRooms = $recent->avg('total_occupancy') ?? $recent->avg('kamar_terjual') ?? 28.0;

        // Apply seasonal adjustment based on target month
        $seasonalFactor = $this->getSeasonalFactor($targetDate->month);
        $adjustedOccupancy = min(100, max(0, $avgOccupancy * $seasonalFactor));
        $adjustedRooms = min(56, max(0, $avgRooms * $seasonalFactor));

        // Use actual hotel room distribution: STD=32, SPR=19, JS=2, FMY=3 (total=56)
        $roomCapacities = config('ml.room_capacities', [
            'STD' => 32,
            'SPR' => 19,
            'JS' => 2,
            'FMY' => 3,
        ]);
        $totalCapacity = array_sum($roomCapacities);

        return (object) [
            'date' => $targetDate,
            'occupancy_rate' => round($adjustedOccupancy, 2),
            'total_occupancy' => round($adjustedRooms, 2),
            'kamar_terjual' => round($adjustedRooms, 2),
            'kamar_std' => round($adjustedRooms * ($roomCapacities['STD'] / $totalCapacity), 2),
            'kamar_spr' => round($adjustedRooms * ($roomCapacities['SPR'] / $totalCapacity), 2),
            'kamar_fmy' => round($adjustedRooms * ($roomCapacities['FMY'] / $totalCapacity), 2),
            'kamar_js' => round($adjustedRooms * ($roomCapacities['JS'] / $totalCapacity), 2),
        ];
    }

    /**
     * Get seasonal adjustment factor based on month
     */
    private function getSeasonalFactor(int $month): float
    {
        // Get peak months from configuration
        $peakMonths = config('ml.peak_months', [6, 7, 12]);

        // Peak season - high demand periods
        if (in_array($month, $peakMonths)) {
            return 1.20; // 20% boost
        }

        // Calculate shoulder months (one month before/after peak)
        $shoulderMonths = [];
        foreach ($peakMonths as $peak) {
            $before = $peak - 1;
            $after = $peak + 1;
            if ($before == 0) $before = 12;
            if ($after == 13) $after = 1;
            $shoulderMonths[] = $before;
            $shoulderMonths[] = $after;
        }
        $shoulderMonths = array_unique($shoulderMonths);

        // Shoulder season - moderate demand
        if (in_array($month, $shoulderMonths)) {
            return 1.05; // 5% boost
        }

        // Low season - lower demand
        return 0.90; // 10% reduction
    }
}
