<?php

namespace App\Services;

/**
 * RecommendationService
 *
 * Sistem Rekomendasi 2-Faktor berbasis Yield Management (Kimes, 1989).
 *
 * Faktor 1: Level Okupansi (dari prediksi LSTM)
 *   Tinggi   : >= 55%
 *   Sedang   : 40–54%
 *   Rendah   : < 40%
 *
 * Faktor 2: Arah Tren (bulan ini vs bulan sebelumnya)
 *   Meningkat : naik > 5 poin persen
 *   Stabil    : ±5 poin persen
 *   Menurun   : turun > 5 poin persen
 *
 * Setiap skenario menghasilkan:
 *  - headline      : kalimat pembuka singkat (konteks situasi)
 *  - action        : tindakan utama yang harus diambil (imperatif, 1 kalimat)
 *  - detail        : penjelasan pendukung (mengapa, apa dampaknya)
 *  - urgency       : tingkat urgensi (high / medium / low)
 */
class RecommendationService
{
    // ──────────────────────────────────────────────────────────────────
    // Matriks 9 skenario
    // ──────────────────────────────────────────────────────────────────
    private const MATRIX = [
        'high' => [
            'increasing' => [
                'headline' => 'Kamar sangat diminati dan permintaan terus naik.',
                'action'   => 'Naikkan harga kamar sekarang untuk memaksimalkan pendapatan.',
                'detail'   => 'Kondisi ini jarang terjadi — manfaatkan sepenuhnya. Siapkan staf housekeeping dan front office ekstra agar tamu terlayani dengan baik di tengah tingginya hunian.',
                'urgency'  => 'high',
            ],
            'stable' => [
                'headline' => 'Kamar ramai dan kondisi stabil.',
                'action'   => 'Pertahankan harga dan layanan saat ini.',
                'detail'   => 'Tidak perlu perubahan strategi besar. Fokus pada kualitas layanan agar tamu puas dan memberikan ulasan positif, yang akan mendukung hunian bulan berikutnya.',
                'urgency'  => 'low',
            ],
            'decreasing' => [
                'headline' => 'Hunian masih tinggi, namun mulai ada tanda-tanda penurunan.',
                'action'   => 'Jangan naikkan harga dulu — tahan strategi promosi yang ada.',
                'detail'   => 'Penurunan ini perlu diwaspadai. Evaluasi apakah ada faktor musiman atau kompetitor yang memengaruhi. Pertahankan program loyalitas dan jaga kualitas agar tamu tetap datang kembali.',
                'urgency'  => 'medium',
            ],
        ],
        'moderate' => [
            'increasing' => [
                'headline' => 'Hunian sedang dan menunjukkan tren yang menggembirakan.',
                'action'   => 'Pertahankan strategi yang berjalan dan siapkan kapasitas untuk lonjakan permintaan.',
                'detail'   => 'Tren positif ini perlu didukung. Pastikan kamar dalam kondisi terbaik dan staf siap jika hunian meningkat lebih lanjut bulan depan.',
                'urgency'  => 'low',
            ],
            'stable' => [
                'headline' => 'Kondisi hunian normal dan stabil.',
                'action'   => 'Tidak ada perubahan mendesak — fokus pada peningkatan kualitas layanan.',
                'detail'   => 'Situasi terkendali. Gunakan periode ini untuk melatih staf, memperbarui fasilitas, atau menyiapkan paket promosi yang akan diaktifkan jika hunian mulai turun.',
                'urgency'  => 'low',
            ],
            'decreasing' => [
                'headline' => 'Hunian sedang namun mulai melemah.',
                'action'   => 'Luncurkan paket promosi atau penawaran khusus sebelum hunian turun lebih jauh.',
                'detail'   => 'Tindakan lebih awal lebih efektif daripada menunggu. Pertimbangkan harga spesial untuk pemesanan lebih awal (early bird), paket menginap plus sarapan, atau kerjasama dengan platform perjalanan online.',
                'urgency'  => 'medium',
            ],
        ],
        'low' => [
            'increasing' => [
                'headline' => 'Hunian rendah tetapi mulai pulih — ada sinyal positif.',
                'action'   => 'Perkuat pemulihan ini dengan promosi bertarget dan harga yang menarik.',
                'detail'   => 'Jangan biarkan momentum ini hilang. Aktifkan iklan digital, tawarkan diskon terbatas waktu, dan pastikan platform pemesanan online menampilkan ketersediaan kamar yang akurat.',
                'urgency'  => 'medium',
            ],
            'stable' => [
                'headline' => 'Hunian rendah dan belum ada tanda perbaikan.',
                'action'   => 'Evaluasi strategi pemasaran dan segera jalin kerjasama dengan agen perjalanan.',
                'detail'   => 'Kondisi ini membutuhkan perhatian serius. Tinjau harga kompetitor, perbaiki foto dan deskripsi kamar di platform online, dan pertimbangkan paket promosi yang lebih agresif untuk menarik tamu baru.',
                'urgency'  => 'high',
            ],
            'decreasing' => [
                'headline' => 'Hunian rendah dan terus menurun — perlu tindakan segera.',
                'action'   => 'Aktifkan kampanye promosi penuh dan turunkan harga ke level kompetitif sekarang.',
                'detail'   => 'Situasi ini memerlukan respons cepat. Hubungi agen perjalanan mitra, aktifkan semua saluran penjualan (OTA, direct booking, corporate), dan pertimbangkan paket bundling atau flash sale untuk mengisi kamar yang kosong.',
                'urgency'  => 'high',
            ],
        ],
    ];

    private const LEVEL_LABELS = [
        'high'     => 'Hunian Tinggi',
        'moderate' => 'Hunian Sedang',
        'low'      => 'Hunian Rendah',
    ];

    private const TREND_LABELS = [
        'increasing' => 'Tren Meningkat',
        'stable'     => 'Tren Stabil',
        'decreasing' => 'Tren Menurun',
    ];

    /**
     * Hasilkan rekomendasi lengkap berdasarkan prediksi bulan ini dan bulan lalu.
     */
    public function getRecommendation(float $occupancyRate, float $previousOccupancy): array
    {
        $level = $this->classifyLevel($occupancyRate);
        $trend = $this->classifyTrend($occupancyRate, $previousOccupancy);

        $scenario = self::MATRIX[$level][$trend];

        // Teks dasar analisis
        $diff = $occupancyRate - $previousOccupancy;
        $diffAbs = abs($diff);

        if ($previousOccupancy > 0) {
            $trendDesc = $diff > 0
                ? "naik {$diffAbs} poin"
                : ($diff < 0 ? "turun {$diffAbs} poin" : "tidak berubah");

            $basisText = sprintf(
                'Prediksi hunian bulan ini %s%%, bulan lalu %s%% (%s dari bulan sebelumnya).',
                number_format($occupancyRate, 1),
                number_format($previousOccupancy, 1),
                $trendDesc
            );
        } else {
            $basisText = sprintf(
                'Prediksi hunian bulan ini %s%%. Data bulan sebelumnya tidak tersedia.',
                number_format($occupancyRate, 1)
            );
        }

        return [
            'level'               => $level,
            'trend'               => $trend,
            'level_label'         => self::LEVEL_LABELS[$level],
            'trend_label'         => self::TREND_LABELS[$trend],
            'headline'            => $scenario['headline'],
            'action'              => $scenario['action'],
            'detail'              => $scenario['detail'],
            'urgency'             => $scenario['urgency'],
            // Backward-compatible alias (digunakan SingleOutput/MultiOutput)
            'recommendation_text' => $scenario['headline'] . ' ' . $scenario['action'] . ' ' . $scenario['detail'],
            'basis_text'          => $basisText,
        ];
    }

    private function classifyLevel(float $rate): string
    {
        if ($rate >= 55) return 'high';
        if ($rate >= 40) return 'moderate';
        return 'low';
    }

    private function classifyTrend(float $current, float $previous): string
    {
        if ($previous <= 0) return 'stable';
        $diff = $current - $previous;
        if ($diff > 5)  return 'increasing';
        if ($diff < -5) return 'decreasing';
        return 'stable';
    }
}
