<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\GeminiController;
use App\Http\Controllers\Api\ReportController;
use App\Services\GeminiService;
use Illuminate\Console\Command;

class ProcessGemini extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'gemini:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process Gemini API requests';

    protected $geminiService;
    protected $geminiController;

    public function __construct(GeminiService $geminiService, GeminiController $geminiController)
    {
        parent::__construct();
        $this->geminiService = $geminiService;
        $this->geminiController = $geminiController;
    }
    /**
     * Execute the console command.
     */
    public function handle()
    {
        $response = $this->geminiController->promptGemini();

        $data = json_decode($response->content(), true);

        $prompt = $this->createAnalysisPrompt($data);

        $result = $this->geminiService->generateAndSaveContent($prompt);

        if ($result->status === 'completed') {
            $this->info('Gemini processing completed successfully');
        } else {
            $this->error("Failed to process Gemini request: {$result->status}");
        }
    }

    private function createAnalysisPrompt(array $data)
    {
        $totalIn = number_format($data['total_product_in']);
        $totalOut = number_format($data['total_product_out']);
        $stockRemaining = number_format($data['total_product_in'] - $data['total_product_out']);
        $today = now()->translatedFormat('l, Y-m-d');
        return  "Saya membutuhkan analisis bisnis mendalam berdasarkan data pergerakan produk berikut:

        📅 **Periode Analisis:** {$data['start_date']} - {$data['end_date']}
        📌 **Tanggal Hari Ini:** {$today}

        ### 📊 **Ringkasan Data**
        ✅ **Total Produk Masuk:** {$totalIn} unit
        ✅ **Total Produk Keluar:** {$totalOut} unit
        ✅ **Sisa Stok Saat Ini:** {$stockRemaining} unit
        ✅ **Product Stock Terbanyak:** {$data['product_many_stock']}
        ✅ **Product Transaksi Terbanyak:** {$data['top_products']}

        ### 🎯 **Tujuan Analisis**
        Bantu saya memahami pola pergerakan stok, mengidentifikasi potensi masalah, serta memberikan rekomendasi strategis. Mohon berikan analisis berdasarkan perspektif berikut:

        1️⃣ **Tren Inventory** – Bagaimana pola perputaran stok dalam periode ini?
        2️⃣ **Insight Performa** – Apakah ada lonjakan atau penurunan signifikan dalam pergerakan produk?
        3️⃣ **Optimalisasi Stok** – Strategi apa yang bisa diterapkan untuk efisiensi stok dan mengurangi dead stock?
        4️⃣ **Risiko & Peluang** – Apakah ada potensi risiko yang perlu diwaspadai atau peluang pertumbuhan yang bisa dimanfaatkan?
        5️⃣ **KPI Utama** – Metrik mana yang perlu dipantau untuk meningkatkan efektivitas manajemen inventory?
        6️⃣ **Strategi Ke Depan** – Rekomendasi actionable untuk meningkatkan operasional dan profitabilitas.

        🔍 **Sebagai seorang Bisnis Analisis, Tolong berikan analisis yang berbasis data, insight yang tajam, serta rekomendasi strategis yang dapat langsung diterapkan.**
        Fokus pada efisiensi bisnis dan potensi pertumbuhan jangka panjang.

        Tolong Gunakan Format agar mudah ditampilkan di FrontEnd seperti berikut
        ### 📊 **Ringkasan Data**
        ✅ **Total Produk Masuk:**
        ✅ **Total Produk Keluar:**
        ✅ **Sisa Stok Saat Ini:**
        ✅ **Product Stock Terbanyak:**
        ✅ **Product Transaksi Terbanyak:**

        ### 🎯 **Tujuan Analisis**
        1️⃣ **Tren Inventory:**
        2️⃣ **Insight Performa:**
        3️⃣ **Optimalisasi Stok:**
        4️⃣ **Risiko & Peluang:**
        5️⃣ **KPI Utama:**
        6️⃣ **Strategi Ke Depan:**
        ### 📋 **Kesimpulan**
        🔍 **Analisis Data:**
        📈 **Rekomendasi:**

        ";
    }
}
