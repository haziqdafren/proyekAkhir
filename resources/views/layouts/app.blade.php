<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Hotel Occupancy Prediction Dashboard')</title>

    <!-- Argon Dashboard CSS -->
    <link href="{{ asset('src/argon-stubs/resources/argon/assets/css/argon-dashboard.css') }}" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #5e72e4;
            --success-color: #2dce89;
            --danger-color: #f5365c;
            --warning-color: #fb6340;
            --info-color: #11cdef;
        }

        body {
            font-family: 'Open Sans', sans-serif;
            background: linear-gradient(87deg, #11cdef 0, #1171ef 100%);
            min-height: 100vh;
        }

        .main-content {
            padding: 30px 15px;
        }

        .card {
            border: none;
            box-shadow: 0 0 2rem 0 rgba(136, 152, 170, 0.15);
            margin-bottom: 24px;
        }

        .card-stats .card-body {
            padding: 1rem 1.5rem;
        }

        .metric-card {
            transition: transform 0.2s;
        }

        .metric-card:hover {
            transform: translateY(-5px);
        }

        .metric-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .chart-container {
            position: relative;
            height: 400px;
        }

        .prediction-form {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 0 2rem 0 rgba(136, 152, 170, 0.15);
        }

        .room-type-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .loading-overlay.active {
            display: flex;
        }

        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 16px;
            margin-bottom: 20px;
        }

        .navbar-custom {
            background: white;
            box-shadow: 0 0 2rem 0 rgba(136, 152, 170, 0.15);
            padding: 1rem 0;
            margin-bottom: 30px;
        }
    </style>

    @stack('styles')
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="text-center text-white">
            <div class="spinner mx-auto mb-3"></div>
            <p class="mb-0">Processing...</p>
        </div>
    </div>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid">
            <a class="navbar-brand d-flex align-items-center" href="{{ route('dashboard') }}">
                <i class="fas fa-hotel text-primary me-2" style="font-size: 1.5rem;"></i>
                <span class="fw-bold">Hotel Dharma Utama</span>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">
                    <i class="fas fa-brain text-info me-2"></i>
                    LSTM Prediction System
                </span>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid main-content">
        @yield('content')
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Common JS -->
    <script>
        // Setup CSRF token for all AJAX requests
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        // Loading overlay helpers
        function showLoading(message = 'Processing...') {
            $('#loadingOverlay').addClass('active');
            $('#loadingOverlay p').text(message);
        }

        function hideLoading() {
            $('#loadingOverlay').removeClass('active');
        }

        // Toast notification helper
        function showToast(message, type = 'info') {
            const bgColors = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            };

            const toast = $(`
                <div class="toast align-items-center text-white ${bgColors[type]} border-0" role="alert" style="position: fixed; top: 20px; right: 20px; z-index: 10000;">
                    <div class="d-flex">
                        <div class="toast-body">${message}</div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `);

            $('body').append(toast);
            const bsToast = new bootstrap.Toast(toast[0]);
            bsToast.show();

            toast.on('hidden.bs.toast', function() {
                $(this).remove();
            });
        }

        // Format number helper
        function formatNumber(num, decimals = 2) {
            return parseFloat(num).toFixed(decimals);
        }

        // Format percentage helper
        function formatPercent(num) {
            return formatNumber(num, 2) + '%';
        }
    </script>

    @stack('scripts')
</body>
</html>
