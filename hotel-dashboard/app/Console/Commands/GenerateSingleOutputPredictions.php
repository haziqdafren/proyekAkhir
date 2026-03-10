<?php

namespace App\Console\Commands;

use App\Models\Prediction;
use App\Services\MLPredictionService;
use App\Services\FeatureEngineeringService;
use App\Services\HistoricalDataAggregationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSingleOutputPredictions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'predictions:generate-single
                            {--months=3 : Number of months ahead to predict (1-3)}
                            {--clear : Clear existing single-output predictions before generating}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly total occupancy predictions using single-output LSTM (1-3 months ahead)';

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
        $this->info('🤖 Generating Single-Output LSTM Predictions (Total Occupancy)...');
        $this->newLine();

        // Get options
        $monthsAhead = (int) $this->option('months');
        $monthsAhead = max(1, min($monthsAhead, 3)); // Limit to 1-3 months

        if ($this->option('clear')) {
            $this->warn('Clearing existing single-output predictions...');
            Prediction::where('model_type', 'single')->delete();
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

            // Step 3: Make predictions using single-output model
            $this->info('🧠 Step 3: Running LSTM single-output model...');
            $result = $this->mlService->predictSingleOutput($features);

            if (!$result['success']) {
                $this->error('❌ Prediction failed');
                return Command::FAILURE;
            }

            $this->info("   Model version: {$result['model_version']}");
            $this->info("   Model MAPE: " . round($result['confidence']['mape'], 2) . '%');
            $this->info("   Model R²: " . round($result['confidence']['r2'], 4));
            $this->newLine();

            // Step 4: Store predictions for next N months
            $this->info("💾 Step 4: Storing {$monthsAhead} months of total occupancy predictions...");
            $this->newLine();

            DB::beginTransaction();

            $storedCount = 0;
            for ($i = 1; $i <= $monthsAhead; $i++) {
                $predictForMonth = Carbon::now()->addMonths($i)->startOfMonth();

                $this->info("   Month +{$i}: {$predictForMonth->format('F Y')}");

                // Calculate revenue estimate (rooms × average price)
                $avgRoomPrice = 500000; // Average room price in IDR
                $estimatedRevenue = $result['prediction']['rooms_sold'] * $avgRoomPrice;

                $predictionRecord = Prediction::create([
                    'room_type_id' => null, // Single-output doesn't have specific room type
                    'prediction_date' => Carbon::now(),
                    'predicted_for_date' => $predictForMonth,
                    'predicted_occupancy_rate' => $result['prediction']['occupancy_rate'],
                    'predicted_rooms_occupied' => (int) round($result['prediction']['rooms_sold']),
                    'predicted_revenue' => $estimatedRevenue,
                    'confidence_level' => 100 - $result['confidence']['mape'], // Higher MAPE = lower confidence
                    'model_type' => 'single',
                    'model_version' => $result['model_version'],
                ]);

                $this->line("      ✓ Total Occupancy: {$result['prediction']['occupancy_rate']}% ({$result['prediction']['rooms_sold']} rooms out of 56)");
                $this->line("         Revenue Estimate: Rp " . number_format($estimatedRevenue, 0, ',', '.'));
                $this->line("         Confidence: " . round(100 - $result['confidence']['mape'], 1) . '%');
                $storedCount++;

                $this->newLine();
            }

            DB::commit();

            // Success summary
            $this->newLine();
            $this->info("✅ Successfully generated {$storedCount} single-output predictions!");
            $this->info("📈 Predictions cover: " . Carbon::now()->addMonth()->format('M Y') . " to " . Carbon::now()->addMonths($monthsAhead)->format('M Y'));

            // Display prediction summary
            $this->newLine();
            $this->displayPredictionSummary($result);

            Log::info('Single-output monthly predictions generated successfully', [
                'months_ahead' => $monthsAhead,
                'predictions_count' => $storedCount,
                'model_version' => $result['model_version'],
                'occupancy_rate' => $result['prediction']['occupancy_rate'],
                'mape' => $result['confidence']['mape'],
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            Log::error('Single-output prediction generation failed', [
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
    private function displayPredictionSummary(array $result): void
    {
        $this->info('📊 Single-Output Prediction Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Rooms Occupied', round($result['prediction']['rooms_sold'], 1) . ' / 56'],
                ['Occupancy Rate', round($result['prediction']['occupancy_rate'], 2) . '%'],
                ['Normalized Value', round($result['prediction']['normalized_value'], 4)],
                ['Model MAPE', round($result['confidence']['mape'], 2) . '%'],
                ['Model R²', round($result['confidence']['r2'], 4)],
                ['Confidence Level', round(100 - $result['confidence']['mape'], 1) . '%'],
            ]
        );
    }
}
