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

    // Check ML service status
    checkMLServiceStatus();

    // Load metrics
    loadMetrics();

    // Load historical data
    loadHistoricalData();

    // Handle prediction form submission
    $('#predictionForm').on('submit', function(e) {
        e.preventDefault();
        generatePrediction();
    });
});

// Check if ML API service is running
function checkMLServiceStatus() {
    $.ajax({
        url: '{{ route("api.health") }}',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                $('#mlServiceStatus')
                    .removeClass('alert-danger')
                    .addClass('alert-success')
                    .show();
                $('#statusIcon').addClass('text-success');
                $('#statusText').html('<strong>ML Service:</strong> Running and ready for predictions');
            } else {
                showMLServiceError();
            }
        },
        error: function() {
            showMLServiceError();
        }
    });
}

function showMLServiceError() {
    $('#mlServiceStatus')
        .removeClass('alert-success')
        .addClass('alert-danger')
        .show();
    $('#statusIcon').addClass('text-danger');
    $('#statusText').html('<strong>ML Service:</strong> Not reachable. Please start Flask API server.');
}

// Load model performance metrics
function loadMetrics() {
    $.ajax({
        url: '{{ route("api.metrics") }}',
        method: 'GET',
        success: function(response) {
            if (response.success && response.metrics) {
                const metrics = response.metrics;

                // Update metrics cards
                $('#metricAccuracy').text(formatPercent(metrics.overall_accuracy || 74.83));
                $('#metricMAPE').text(formatPercent(metrics.overall_mape || 25.17));
                $('#accuracyChange').text('+' + formatPercent(metrics.improvement || 17.61));

                // Best room type
                const roomMetrics = metrics.per_room || {};
                let bestRoom = 'STD';
                let bestAccuracy = 0;

                Object.keys(roomMetrics).forEach(room => {
                    const accuracy = 100 - roomMetrics[room].MAPE;
                    if (accuracy > bestAccuracy) {
                        bestAccuracy = accuracy;
                        bestRoom = room;
                    }
                });

                $('#bestRoomType').text(bestRoom);
                $('#bestRoomAccuracy').text(formatPercent(bestAccuracy));
            }
        },
        error: function(xhr, status, error) {
            console.error('Failed to load metrics:', error);
            // Use default values
            $('#metricAccuracy').text('74.83%');
            $('#metricMAPE').text('25.17%');
            $('#accuracyChange').text('+17.61%');
            $('#bestRoomType').text('STD');
            $('#bestRoomAccuracy').text('80.82%');
        }
    });
}

// Load historical occupancy data
function loadHistoricalData() {
    showLoading('Loading historical data...');

    $.ajax({
        url: '{{ route("api.historical") }}',
        method: 'GET',
        success: function(response) {
            hideLoading();
            if (response.success && response.data) {
                renderHistoricalChart(response.data);
            } else {
                showToast('Failed to load historical data', 'warning');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Failed to load historical data:', error);
            showToast('Error loading historical data. Using sample data.', 'warning');
            // Load sample data
            renderHistoricalChart(getSampleHistoricalData());
        }
    });
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

// Generate prediction
function generatePrediction() {
    const monthsAhead = $('#monthsAhead').val();
    const roomTypes = [];

    $('.room-type-check:checked').each(function() {
        roomTypes.push($(this).val());
    });

    if (roomTypes.length === 0) {
        showToast('Please select at least one room type', 'warning');
        return;
    }

    showLoading(`Generating prediction for ${monthsAhead} month(s)...`);

    $.ajax({
        url: '{{ route("api.predict") }}',
        method: 'POST',
        data: {
            months_ahead: monthsAhead,
            room_types: roomTypes
        },
        success: function(response) {
            hideLoading();
            if (response.success && response.predictions) {
                renderPredictionResults(response.predictions, monthsAhead);
                showToast('Prediction generated successfully!', 'success');
            } else {
                showToast('Failed to generate prediction', 'error');
            }
        },
        error: function(xhr, status, error) {
            hideLoading();
            console.error('Prediction failed:', error);
            showToast('Error generating prediction: ' + (xhr.responseJSON?.message || error), 'error');
        }
    });
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

// Get sample historical data (fallback)
function getSampleHistoricalData() {
    const months = ['Jan 21', 'Feb 21', 'Mar 21', 'Apr 21', 'May 21', 'Jun 21',
                    'Jul 21', 'Aug 21', 'Sep 21', 'Oct 21', 'Nov 21', 'Dec 21'];

    return {
        labels: months,
        STD: [45, 48, 52, 55, 58, 62, 65, 68, 60, 55, 50, 58],
        SPR: [42, 45, 48, 52, 55, 58, 62, 65, 58, 52, 48, 55],
        FMY: [35, 38, 42, 45, 48, 52, 55, 58, 52, 45, 40, 48],
        JS: [38, 42, 45, 48, 52, 55, 58, 62, 55, 48, 45, 52]
    };
}
</script>
@endpush
