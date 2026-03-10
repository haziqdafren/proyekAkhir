<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Prediksi Okupansi</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 10px; }
        h1 { color: #2E5266; text-align: center; font-size: 18px; margin-bottom: 5px; }
        h2 { font-size: 12px; color: #666; text-align: center; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background-color: #2E5266; color: white; padding: 8px; text-align: left; font-size: 9px; }
        td { padding: 6px; border-bottom: 1px solid #ddd; font-size: 9px; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .footer { margin-top: 30px; text-align: center; font-size: 8px; color: #999; }
    </style>
</head>
<body>
    <h1>Laporan Prediksi Okupansi Hotel</h1>
    <h2>Dharma Hotel Dashboard - Sistem Prediksi Berbasis LSTM</h2>

    <table>
        <thead>
            <tr>
                <th>Tanggal Prediksi</th>
                <th>Bulan Untuk</th>
                <th>Tipe Kamar</th>
                <th>Okupansi (%)</th>
                <th>Kamar Terisi</th>
                <th>Total Kamar</th>
                <th>Revenue (Rp)</th>
                <th>Model</th>
                <th>MAPE (%)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($predictions as $prediction)
            <tr>
                <td>{{ $prediction->prediction_date ? \Carbon\Carbon::parse($prediction->prediction_date)->format('d/m/Y H:i') : '-' }}</td>
                <td>{{ $prediction->predicted_for_date ? \Carbon\Carbon::parse($prediction->predicted_for_date)->format('M Y') : '-' }}</td>
                <td>{{ $prediction->roomType ? $prediction->roomType->name : 'Total Hotel' }}</td>
                <td>{{ number_format($prediction->predicted_occupancy_rate, 2, ',', '.') }}</td>
                <td>{{ $prediction->predicted_rooms_occupied ?? 0 }}</td>
                <td>{{ $prediction->roomType ? $prediction->roomType->total_rooms : 0 }}</td>
                <td>{{ number_format($prediction->predicted_revenue, 0, ',', '.') }}</td>
                <td>{{ strtoupper($prediction->model_type) }}</td>
                <td>{{ $prediction->model_type === 'single' ? '17,18' : '32,39' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generated on {{ date('d/m/Y H:i:s') }} | Dharma Hotel Management System</p>
    </div>
</body>
</html>
