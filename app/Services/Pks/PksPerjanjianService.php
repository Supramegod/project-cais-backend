<?php

namespace App\Services\Pks;

use App\Models\Pks;
use App\Models\PksPerjanjian;
use App\Models\PksPerjanjianHistory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SebastianBergmann\Diff\Differ;
use SebastianBergmann\Diff\Output\UnifiedDiffOutputBuilder;

class PksPerjanjianService
{
    public function __construct(
        private PksPasalService $pasalService
    ) {}

    public function updatePerjanjian($id, $judul, $rawText): array
    {
        $perjanjian = PksPerjanjian::findOrFail($id);
        $judulBaru = $judul ?? $perjanjian->judul;

        if ($perjanjian->raw_text === $rawText && $perjanjian->judul === $judulBaru) {
            return ['perjanjian' => $perjanjian, 'changed' => false];
        }

        $updated = DB::transaction(function () use ($perjanjian, $judulBaru, $rawText) {
            PksPerjanjianHistory::create([
                'pks_perjanjian_id' => $perjanjian->id,
                'pks_id' => $perjanjian->pks_id,
                'pasal' => $perjanjian->pasal,
                'judul' => $perjanjian->judul,
                'raw_text' => $perjanjian->raw_text,
                'snapshot' => json_encode($perjanjian->toArray(), JSON_PRETTY_PRINT),
                'changed_by' => Auth::user()->full_name,
            ]);

            $perjanjian->update([
                'judul' => $judulBaru,
                'raw_text' => $rawText,
                'updated_by' => Auth::user()->full_name,
            ]);

            $pks = Pks::with('leads')->find($perjanjian->pks_id);
            if ($pks && $pks->leads) {
                $this->pasalService->logPerjanjianChange($perjanjian, $pks->leads);
            }

            return $perjanjian;
        });

        return ['perjanjian' => $updated, 'changed' => true];
    }

    public function getPerjanjianHistoryData($id): \Illuminate\Support\Collection
    {
        $perjanjian = PksPerjanjian::find($id);
        if (!$perjanjian) {
            throw new ModelNotFoundException('Perjanjian not found');
        }

        $history = PksPerjanjianHistory::where('pks_perjanjian_id', $id)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'judul', 'raw_text', 'changed_by', 'created_at']);

        return $history->map(fn($h) => [
            'id' => $h->id,
            'judul' => $h->judul,
            'changed_by' => $h->changed_by,
            'waktu' => $h->created_at->format('d-m-Y H:i:s'),
        ]);
    }

    public function comparePerjanjianData($pksPerjanjianId, $historyId = null): array
    {
        $perjanjian = PksPerjanjian::findOrFail($pksPerjanjianId);
        $newText = $perjanjian->raw_text;
        $newJudul = $perjanjian->judul;
        $labelNew = 'Saat ini (terbaru)';

        if ($historyId) {
            $history = PksPerjanjianHistory::find($historyId);
            if (!$history) {
                throw new ModelNotFoundException('History tidak ditemukan');
            }
            $oldText = $history->raw_text;
            $oldJudul = $history->judul;
            $labelOld = 'Versi ' . $history->created_at->format('d-m-Y H:i');
        } else {
            $latestHistory = PksPerjanjianHistory::where('pks_perjanjian_id', $perjanjian->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$latestHistory) {
                throw new \RuntimeException('Tidak ada riwayat perubahan untuk perjanjian ini.');
            }

            $oldText = $latestHistory->raw_text;
            $oldJudul = $latestHistory->judul;
            $labelOld = 'Sebelum edit (' . $latestHistory->created_at->format('d-m-Y H:i') . ')';
        }

        $judulChanged = ($oldJudul !== $newJudul);
        $diff = null;

        if ($oldText !== $newText) {
            try {
                $oldLines = preg_split('/\r\n|\r|\n/', $oldText);
                $newLines = preg_split('/\r\n|\r|\n/', $newText);
                $outputBuilder = new UnifiedDiffOutputBuilder("--- Original\n+++ New\n");
                $differ = new Differ($outputBuilder);
                $diff = $differ->diff($oldLines, $newLines);
            } catch (\Exception $e) {
                $diff = null;
            }
        }

        return [
            'version_label_old' => $labelOld,
            'version_label_new' => $labelNew,
            'judul_old' => $oldJudul,
            'judul_new' => $newJudul,
            'judul_changed' => $judulChanged,
            'diff_unified' => $diff,
            'old_text' => $oldText,
            'new_text' => $newText,
        ];
    }
}
