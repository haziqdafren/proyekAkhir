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
            $endDate = Carbon::now()->subMonth(); // Last complete month
            $startDate = $endDate->copy()->subMonths(11); // 12 months total

            $aggregatedData = $this->aggregationService->getAggregatedMonthlyData($startDate, $endDate);

            $this->info("   Found {$aggregatedData->count()} months of data ({$startDate->format('Y-m')} to {$endDate->format('Y-m')})");

            if ($aggregatedData->count() < 6) {
                $this->error("❌ Insufficient historical data. Need at least 6 months, found: {$aggregatedData->count()}");
                return Command::FAILURE;
            }

            // Step 2: Prepare features (6 months × 15 features)
            $this->info('🔧 Step 2: Engineering features...');
            $features = $this->featureService->prepareFeatures($aggregatedData);

            if (!$this->featureService->validateFeatureArray($features)) {
                $this->error('❌ Feature validation failed. Expected shape: [6, 15]');
                return Command::FAILURE;
            }

            $this->info('   Features prepared: 6 months × 15 features');
            $this->displayFeatureSample($features);

            // Step 3: Get room capacities
            $roomCapacities = $this->mlService->getRoomCapacities();
            $this->info('   Room capacities: ' . json_encode($roomCapacities));

            // Step 4: Make predictions using multi-output model
            $this->info('🧠 Step 3: Running LSTM multi-output model...');
            $result = $this->mlService->predictMultiOutput($features, $roomCapacities);

            if (!$result['success']) {
                $this->error('❌ Prediction failed');
                return Command::FAILURE;
            }

            $this->info("   Model version: {$result['model_version']}");
            $this->info("   Overall MAPE: " . round($result['overall_mape'], 2) . '%');
            $this->newLine();

            // Step 5: Store predictions for next N months
            $this->info("💾 Step 4: Storing {$monthsAhead} months of predictions...");
            $this->newLine();

            DB::beginTransaction();

            $storedCount = 0;
            for ($i = 1; $i <= $monthsAhead; $i++) {
                $predictForMonth = Carbon::now()->addMonths($i)->startOfMonth();

                $this->info("   Month +{$i}: {$predictForMonth->format('F Y')}");

                foreach ($result['predictions'] as $roomCode => $prediction) {
                    $roomType = RoomType::where('code', $roomCode)->first();

                    if (!$roomType) {
                        $this->warn("      ⚠️  Room type '{$roomCode}' not found in database");
                        continue;
                    }

                    $predictionRecord = Prediction::create([
                        'room_type_id' => $roomType->id,
                        'prediction_date' => Carbon::now(),
                        'predicted_for_date' => $predictForMonth,
                        'predicted_occupancy_rate' => $prediction['occupancy_rate'],
                        'predicted_rooms_occupied' => (int) $prediction['rooms_sold'],
                        'predicted_revenue' => $prediction['rooms_sold'] * $roomType->base_price,
                        'confidence_level' => 100 - $prediction['mape'], // Higher MAPE = lower confidence
                        'model_type' => 'multi',
                        'model_version' => $result['model_version'],
                    ]);

                    $this->line("      ✓ {$roomType->name}: {$prediction['occupancy_rate']}% ({$prediction['rooms_sold']} rooms) - Confidence: " . round(100 - $prediction['mape'], 1) . '%');
                    $storedCount++;
                }

                $this->newLine();
            }

            DB::commit();

            // Success summary
            $this->newLine();
            $this->info("✅ Successfully generated {$storedCount} predictions!");
            $this->info("📈 Predictions cover: " . Carbon::now()->addMonth()->format('M Y') . " to " . Carbon::now()->addMonths($monthsAhead)->format('M Y'));

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
}
