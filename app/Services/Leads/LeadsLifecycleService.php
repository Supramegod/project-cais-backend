<?php

namespace App\Services\Leads;

use App\Models\Leads;
use App\Models\LeadsKebutuhan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LeadsLifecycleService
{
    /**
     * Soft delete lead beserta kebutuhan terkait (transactional).
     */
    public function deleteLead(Leads $lead): void
    {
        DB::transaction(function () use ($lead) {
            $id = $lead->id;
            $lead->deleted_by = Auth::user()->full_name;
            $lead->save();
            $lead->delete();

            $deletedBy = Auth::user()->full_name;

            LeadsKebutuhan::where('leads_id', $id)
                ->update([
                    'deleted_by' => $deletedBy,
                    'deleted_at' => now(),
                ]);
        });
    }

    /**
     * Restore lead beserta kebutuhan terkait (transactional).
     */
    public function restoreLead(Leads $lead): void
    {
        DB::transaction(function () use ($lead) {
            $id = $lead->id;
            $lead->restore();
            $lead->deleted_by = null;
            $lead->save();
            LeadsKebutuhan::onlyTrashed()
                ->where('leads_id', $id)
                ->update([
                    'deleted_at' => null,
                    'deleted_by' => null,
                ]);
        });
    }

    /**
     * Aktifkan lead (transactional).
     */
    public function activateLead(Leads $lead): void
    {
        DB::transaction(function () use ($lead) {
            $lead->is_aktif = 1;
            $lead->updated_by = Auth::user()->full_name;
            $lead->save();
        });
    }

    /**
     * Generate nomor untuk semua leads yang belum memiliki nomor (transactional).
     */
    public function generateNullKode(): void
    {
        DB::transaction(function () {
            $leads = Leads::whereNull('nomor')->whereNull('deleted_at')->get();
            $nomor = "";

            foreach ($leads as $key => $lead) {
                if ($key == 0) {
                    $nomor = $this->generateNomor();
                } else {
                    $nomor = $this->generateNomorLanjutan($nomor);
                }

                $lead->update(['nomor' => $nomor]);
            }
        });
    }

    private function generateNomor()
    {
        $lastLeads = Leads::latest('id')->first();

        if (!$lastLeads?->nomor) {
            return 'AAAAA';
        }

        $nomor = $lastLeads->nomor;
        $chars = str_split($nomor);

        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $current = $chars[$i];

            if (is_numeric($current)) {
                if ($current < '9') {
                    $chars[$i] = (string) ($current + 1);
                    break;
                } else {
                    $chars[$i] = 'A';
                    break;
                }
            }

            if (ctype_alpha($current)) {
                if ($current < 'Z') {
                    $chars[$i] = chr(ord($current) + 1);
                    break;
                } else {
                    $chars[$i] = '0';
                    continue;
                }
            }
        }

        return str_pad(implode('', $chars), 5, 'A', STR_PAD_RIGHT);
    }

    private function generateNomorLanjutan($nomor)
    {
        $chars = str_split($nomor);
        for ($i = count($chars) - 1; $i >= 0; $i--) {
            $ascii = ord($chars[$i]);
            if (($ascii >= 48 && $ascii < 57) || ($ascii >= 65 && $ascii < 90)) {
                $ascii += 1;
            } else if ($ascii == 90) {
                $ascii = 48;
            } else {
                continue;
            }
            $ascchar = chr($ascii);
            $nomor = substr_replace($nomor, $ascchar, $i);
            break;
        }
        if (strlen($nomor) < 5) {
            $jumlah = 5 - strlen($nomor);
            for ($i = 0; $i < $jumlah; $i++) {
                $nomor = $nomor . "A";
            }
        }
        return $nomor;
    }
}
