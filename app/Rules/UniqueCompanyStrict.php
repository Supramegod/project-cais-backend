<?php
namespace App\Rules;

use App\Models\Leads;
use App\Models\Village;
use App\Models\Benua;
use App\Models\City;
use App\Models\Province;
use App\Models\District;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Http;

class UniqueCompanyStrict implements Rule
{
    protected $excludeId;
    protected $similarCompanies = [];
    protected $geographicNames = [];
    protected $commonWords = [
        'pt', 'cv', 'ud', 'tbk', 'persero', 'perusahaan', 'company', 'corp', 'corporation',
        'inc', 'ltd', 'group', 'holding', 'international', 'global', 'national', 'nasional',
        'pusat', 'cabang', 'kantor', 'toko', 'warung', 'industri', 'enterprise', 'services',
        'service', 'solution', 'tech', 'technology', 'technologies', 'the', 'and', 'or',
        'of', 'in', 'at', 'on', 'for', 'to', 'dan', 'atau', 'dari', 'di', 'ke', 'pada', 'untuk'
    ];

    public function __construct($excludeId = null)
    {
        $this->excludeId = $excludeId;
        $this->loadGeographicNames();
    }

    private function loadGeographicNames()
    {
        $villages = Village::pluck('name')->map(fn($n) => strtolower(trim($n)))->filter()->toArray();
        $benuas = Benua::pluck('nama_benua')->map(fn($n) => strtolower(trim($n)))->filter()->toArray();
        $cities = City::where('is_active', 1)->pluck('name')->map(fn($n) => strtolower(trim($n)))->filter()->toArray();
        $provinces = Province::where('is_active', 1)->pluck('name')->map(fn($n) => strtolower(trim($n)))->filter()->toArray();
        $districts = District::pluck('name')->map(fn($n) => strtolower(trim($n)))->filter()->toArray();

        $this->geographicNames = array_unique(array_merge($villages, $benuas, $cities, $provinces, $districts));
    }

    public function passes($attribute, $value)
    {
        // 1. Filtering awal dari database
        $firstWord = explode(' ', trim($value))[0];
        $query = Leads::whereNull('deleted_at');
        if ($this->excludeId) { $query->where('id', '!=', $this->excludeId); }

        $candidates = $query->where('nama_perusahaan', 'LIKE', "%{$firstWord}%")
                            ->limit(30)
                            ->pluck('nama_perusahaan')
                            ->toArray();

        if (empty($candidates)) { return true; }

        // 2. Kirim ke AI dengan konteks lengkap
        return $this->askAI($value, $candidates);
    }

    private function askAI($input, $candidates)
    {
        $apiKey = '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';
        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        
        // Ringkas data untuk menghemat token
        $geoContext = implode(',', array_slice($this->geographicNames, 0, 50)); 
        $commonContext = implode(',', $this->commonWords);
        $listCandidates = implode("\n- ", $candidates);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(20)->post($endpoint, [
                'model' => 'deepseek/deepseek-chat',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Anda adalah validator database perusahaan Indonesia. 
                        Tugas: Cek apakah 'Input Baru' sudah ada di 'Database' secara substansi.
                        
                        Instruksi Penting:
                        1. Abaikan kata umum/badan hukum: [$commonContext].
                        2. Abaikan nama geografis/lokasi jika hanya sebagai pelengkap: [$geoContext].
                        3. 'PT Maju Jaya' dan 'Maju Jaya Group' dianggap SAMA.
                        
                        Jika ditemukan yang mirip, balas: 'MIRIP: [Nama di Database]'.
                        Jika tidak ada yang mirip, balas: 'AMAN'."
                    ],
                    [
                        'role' => 'user',
                        'content' => "Input Baru: $input\n\nDatabase:\n- $listCandidates"
                    ]
                ],
            ]);

            if ($response->successful()) {
                $result = $response->json()['choices'][0]['message']['content'] ?? 'AMAN';
                if (str_contains(strtoupper($result), 'MIRIP')) {
                    $this->similarCompanies[] = $result;
                    return false;
                }
            }
        } catch (\Exception $e) {
            \Log::error('AI Validation Fail: ' . $e->getMessage());
        }
        return true;
    }

    public function message() {
        return 'Peringatan: ' . ($this->similarCompanies[0] ?? 'Nama perusahaan terdeteksi sudah ada.');
    }
}