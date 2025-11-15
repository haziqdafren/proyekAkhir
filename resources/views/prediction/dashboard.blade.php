@extends('layouts.app')

@section('title', 'Occupancy Prediction Dashboard - Hotel Dharma Utama')

@section('content')
<div class="container-fluid">

    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h2 class="mb-2">
                        <i class="fas fa-chart-line text-primary me-2"></i>
                        Prediksi Tingkat Okupansi Hotel
                    </h2>
                    <p class="text-muted mb-0">
                        Dashboard prediksi okupansi menggunakan LSTM Neural Network untuk 4 tipe kamar
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- ML Service Status -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-custom" id="mlServiceStatus" style="display: none;">
                <div class="d-flex align-items-center">
                    <i class="fas fa-circle me-2" id="statusIcon"></i>
                    <span id="statusText"></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Model Performance Metrics -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="card card-stats metric-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Model Accuracy</h5>
                            <span class="h2 font-weight-bold mb-0" id="metricAccuracy">--</span>
                        </div>
                        <div class="col-auto">
                            <div class="metric-icon bg-gradient-success text-white">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-success mr-2" id="accuracyChange">--</span>
                        <span class="text-nowrap text-muted">dari baseline</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card card-stats metric-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">MAPE Error</h5>
                            <span class="h2 font-weight-bold mb-0" id="metricMAPE">--</span>
                        </div>
                        <div class="col-auto">
                            <div class="metric-icon bg-gradient-warning text-white">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-success mr-2">Target: ≤35%</span>
                        <span class="text-nowrap text-muted">achieved</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card card-stats metric-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Best Room Type</h5>
                            <span class="h2 font-weight-bold mb-0" id="bestRoomType">--</span>
                        </div>
                        <div class="col-auto">
                            <div class="metric-icon bg-gradient-info text-white">
                                <i class="fas fa-bed"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-success mr-2" id="bestRoomAccuracy">--</span>
                        <span class="text-nowrap text-muted">accuracy</span>
                    </p>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="card card-stats metric-card">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <h5 class="card-title text-uppercase text-muted mb-0">Model Size</h5>
                            <span class="h2 font-weight-bold mb-0">12.0K</span>
                        </div>
                        <div class="col-auto">
                            <div class="metric-icon bg-gradient-primary text-white">
                                <i class="fas fa-microchip"></i>
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 mb-0 text-sm">
                        <span class="text-success mr-2">-72.5%</span>
                        <span class="text-nowrap text-muted">vs baseline</span>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="row">
        <!-- Left Column: Prediction Form -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-gradient-primary">
                    <h5 class="text-white mb-0">
                        <i class="fas fa-magic me-2"></i>
                        Generate Prediction
                    </h5>
                </div>
                <div class="card-body">
                    <form id="predictionForm">
                        <!-- Months Ahead -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-calendar-alt text-primary me-2"></i>
                                Prediksi untuk berapa bulan ke depan?
                            </label>
                            <select class="form-select" id="monthsAhead" name="months_ahead" required>
                                <option value="1">1 Bulan</option>
                                <option value="3" selected>3 Bulan</option>
                                <option value="6">6 Bulan</option>
                                <option value="12">12 Bulan</option>
                            </select>
                            <div class="form-text">Model akan memprediksi okupansi untuk periode yang dipilih</div>
                        </div>

                        <!-- Room Types Selection -->
                        <div class="mb-4">
                            <label class="form-label fw-bold">
                                <i class="fas fa-door-open text-primary me-2"></i>
                                Pilih Tipe Kamar
                            </label>
                            <div class="form-check">
                                <input class="form-check-input room-type-check" type="checkbox" value="STD" id="roomSTD" checked>
                                <label class="form-check-label" for="roomSTD">
                                    <span class="room-type-badge bg-primary text-white">Standard (STD)</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input room-type-check" type="checkbox" value="SPR" id="roomSPR" checked>
                                <label class="form-check-label" for="roomSPR">
                                    <span class="room-type-badge bg-success text-white">Superior (SPR)</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input room-type-check" type="checkbox" value="FMY" id="roomFMY" checked>
                                <label class="form-check-label" for="roomFMY">
                                    <span class="room-type-badge bg-warning text-white">Family (FMY)</span>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input room-type-check" type="checkbox" value="JS" id="roomJS" checked>
                                <label class="form-check-label" for="roomJS">
                                    <span class="room-type-badge bg-info text-white">Junior Suite (JS)</span>
                                </label>
                            </div>
                            <div class="form-text">Pilih minimal 1 tipe kamar untuk diprediksi</div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-chart-line me-2"></i>
                            Generate Prediction
                        </button>
                    </form>

                    <!-- Current Month Info -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-info-circle me-2"></i>
                            Current Period
                        </h6>
                        <p class="mb-1"><strong>Bulan:</strong> <span id="currentMonth"></span></p>
                        <p class="mb-0"><strong>Data terakhir:</strong> Oktober 2025</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Charts -->
        <div class="col-lg-8">
            <!-- Historical Data Chart -->
            <div class="card mb-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="fas fa-history text-info me-2"></i>
                        Historical Occupancy Trend (2021-2025)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="historicalChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Prediction Results Chart -->
            <div class="card" id="predictionResultCard" style="display: none;">
                <div class="card-header bg-gradient-success">
                    <h5 class="text-white mb-0">
                        <i class="fas fa-chart-area text-white me-2"></i>
                        Prediction Results
                    </h5>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="predictionChart"></canvas>
                    </div>

                    <!-- Prediction Summary Table -->
                    <div class="mt-4">
                        <h6 class="mb-3">Prediction Summary</h6>
                        <div class="table-responsive">
                            <table class="table table-hover" id="predictionSummaryTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Period</th>
                                        <th>STD</th>
                                        <th>SPR</th>
                                        <th>FMY</th>
                                        <th>JS</th>
                                        <th>Average</th>
                                    </tr>
                                </thead>
                                <tbody id="predictionSummaryBody">
                                    <!-- Filled by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
let historicalChart = null;
let predictionChart = null;

$(document).ready(function() {
    // Set current month
    const now = new Date();
    const monthNames = ["Januari", "Februari", "Maret", "April", "Mei", "Juni",
        "Juli", "Agustus", "September", "Oktober", "November", "Desember"];
    $('#currentMonth').text(`${monthNames[now.getMonth()]} ${now.getFullYear()}`);

    // Show demo mode status
    showDemoModeStatus();

    // Load metrics with sample data
    loadMetrics();

    // Load historical data with sample data
    loadHistoricalData();

    // Handle prediction form submission
    $('#predictionForm').on('submit', function(e) {
        e.preventDefault();
        generatePrediction();
    });
});

// Show demo mode status (frontend only)
function showDemoModeStatus() {
    $('#mlServiceStatus')
        .removeClass('alert-danger')
        .addClass('alert-info')
        .show();
    $('#statusIcon').addClass('text-info');
    $('#statusText').html('<strong>Demo Mode:</strong> Dashboard menggunakan sample data untuk demonstrasi frontend');
}

// Load model performance metrics (sample data)
function loadMetrics() {
    // Use sample metrics data
    $('#metricAccuracy').text('74.83%');
    $('#metricMAPE').text('25.17%');
    $('#accuracyChange').text('+17.61%');
    $('#bestRoomType').text('STD');
    $('#bestRoomAccuracy').text('80.82%');
}

// Load historical occupancy data (sample data)
function loadHistoricalData() {
    showLoading('Loading historical data...');

    setTimeout(() => {
        hideLoading();
        renderHistoricalChart(getSampleHistoricalData());
    }, 500);
}

// Render historical data chart
function renderHistoricalChart(data) {
    const ctx = document.getElementById('historicalChart').getContext('2d');

    if (historicalChart) {
        historicalChart.destroy();
    }

    historicalChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels || [],
            datasets: [
                {
                    label: 'Standard (STD)',
                    data: data.STD || [],
                    borderColor: '#5e72e4',
                    backgroundColor: 'rgba(94, 114, 228, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Superior (SPR)',
                    data: data.SPR || [],
                    borderColor: '#2dce89',
                    backgroundColor: 'rgba(45, 206, 137, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Family (FMY)',
                    data: data.FMY || [],
                    borderColor: '#fb6340',
                    backgroundColor: 'rgba(251, 99, 64, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Junior Suite (JS)',
                    data: data.JS || [],
                    borderColor: '#11cdef',
                    backgroundColor: 'rgba(17, 205, 239, 0.1)',
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                title: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });
}

// Generate prediction (sample data - frontend only)
function generatePrediction() {
    const monthsAhead = parseInt($('#monthsAhead').val());
    const roomTypes = [];

    $('.room-type-check:checked').each(function() {
        roomTypes.push($(this).val());
    });

    if (roomTypes.length === 0) {
        showToast('Please select at least one room type', 'warning');
        return;
    }

    showLoading(`Generating prediction for ${monthsAhead} month(s)...`);

    // Simulate API call delay
    setTimeout(() => {
        hideLoading();

        // Generate sample predictions
        const predictions = generateSamplePredictions(roomTypes, monthsAhead);

        renderPredictionResults(predictions, monthsAhead);
        showToast('Prediction generated successfully!', 'success');
    }, 1500);
}

// Generate sample predictions based on room types and months
function generateSamplePredictions(roomTypes, monthsAhead) {
    const predictions = {};

    // Base occupancy rates for each room type
    const baseRates = {
        'STD': 75,
        'SPR': 68,
        'FMY': 62,
        'JS': 65
    };

    roomTypes.forEach(roomType => {
        const baseRate = baseRates[roomType];
        const values = [];

        for (let i = 0; i < monthsAhead; i++) {
            // Add some variation (+/- 10%)
            const variation = (Math.random() - 0.5) * 20;
            let value = baseRate + variation;

            // Add seasonal trend (peak in middle months)
            const seasonalBoost = Math.sin((i / monthsAhead) * Math.PI) * 8;
            value += seasonalBoost;

            // Keep within 40-95% range
            value = Math.max(40, Math.min(95, value));
            values.push(parseFloat(value.toFixed(2)));
        }

        predictions[roomType] = values;
    });

    return predictions;
}

// Render prediction results
function renderPredictionResults(predictions, monthsAhead) {
    $('#predictionResultCard').slideDown();

    // Prepare data for chart
    const labels = [];
    const datasets = [];
    const colors = {
        'STD': '#5e72e4',
        'SPR': '#2dce89',
        'FMY': '#fb6340',
        'JS': '#11cdef'
    };

    // Generate month labels
    const now = new Date();
    for (let i = 1; i <= monthsAhead; i++) {
        const futureDate = new Date(now.getFullYear(), now.getMonth() + i, 1);
        labels.push(futureDate.toLocaleDateString('id-ID', { year: 'numeric', month: 'short' }));
    }

    // Create datasets for each room type
    Object.keys(predictions).forEach(roomType => {
        datasets.push({
            label: roomType,
            data: predictions[roomType],
            borderColor: colors[roomType],
            backgroundColor: colors[roomType] + '20',
            tension: 0.4,
            fill: true
        });
    });

    // Render chart
    const ctx = document.getElementById('predictionChart').getContext('2d');

    if (predictionChart) {
        predictionChart.destroy();
    }

    predictionChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                title: {
                    display: true,
                    text: `Predicted Occupancy for Next ${monthsAhead} Month(s)`
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    ticks: {
                        callback: function(value) {
                            return value + '%';
                        }
                    }
                }
            }
        }
    });

    // Fill summary table
    fillPredictionSummaryTable(predictions, labels);
}

// Fill prediction summary table
function fillPredictionSummaryTable(predictions, labels) {
    const tbody = $('#predictionSummaryBody');
    tbody.empty();

    const roomTypes = Object.keys(predictions);
    const numMonths = predictions[roomTypes[0]].length;

    for (let i = 0; i < numMonths; i++) {
        let row = `<tr><td><strong>${labels[i]}</strong></td>`;
        let sum = 0;
        let count = 0;

        ['STD', 'SPR', 'FMY', 'JS'].forEach(room => {
            if (predictions[room]) {
                const value = predictions[room][i];
                row += `<td>${formatPercent(value)}</td>`;
                sum += value;
                count++;
            } else {
                row += `<td class="text-muted">-</td>`;
            }
        });

        const avg = count > 0 ? sum / count : 0;
        row += `<td><strong>${formatPercent(avg)}</strong></td></tr>`;
        tbody.append(row);
    }
}

// Get sample historical data (2021-2025)
function getSampleHistoricalData() {
    const months = [
        'Jan 21', 'Feb 21', 'Mar 21', 'Apr 21', 'May 21', 'Jun 21',
        'Jul 21', 'Aug 21', 'Sep 21', 'Oct 21', 'Nov 21', 'Dec 21',
        'Jan 22', 'Feb 22', 'Mar 22', 'Apr 22', 'May 22', 'Jun 22',
        'Jul 22', 'Aug 22', 'Sep 22', 'Oct 22', 'Nov 22', 'Dec 22',
        'Jan 23', 'Feb 23', 'Mar 23', 'Apr 23', 'May 23', 'Jun 23',
        'Jul 23', 'Aug 23', 'Sep 23', 'Oct 23', 'Nov 23', 'Dec 23',
        'Jan 24', 'Feb 24', 'Mar 24', 'Apr 24', 'May 24', 'Jun 24',
        'Jul 24', 'Aug 24', 'Sep 24', 'Oct 24', 'Nov 24', 'Dec 24',
        'Jan 25', 'Feb 25', 'Mar 25', 'Apr 25', 'May 25', 'Jun 25',
        'Jul 25', 'Aug 25', 'Sep 25', 'Oct 25'
    ];

    // Generate realistic occupancy data with trends
    const generateOccupancyData = (baseRate, variance) => {
        const data = [];
        for (let i = 0; i < months.length; i++) {
            // Trend: gradually increasing over years
            const yearTrend = (i / 12) * 2;

            // Seasonal: peak in middle of year, low at start/end
            const seasonal = Math.sin((i % 12) / 12 * 2 * Math.PI) * 8;

            // Random variation
            const random = (Math.random() - 0.5) * variance;

            let value = baseRate + yearTrend + seasonal + random;
            value = Math.max(40, Math.min(95, value));
            data.push(parseFloat(value.toFixed(2)));
        }
        return data;
    };

    return {
        labels: months,
        STD: generateOccupancyData(72, 8),  // Standard: higher base rate
        SPR: generateOccupancyData(65, 10), // Superior: medium rate
        FMY: generateOccupancyData(58, 12), // Family: lower, more variance
        JS: generateOccupancyData(62, 10)   // Junior Suite: medium-low
    };
}
</script>
@endpush
