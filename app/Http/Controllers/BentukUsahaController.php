<?php

namespace App\Http\Controllers;

use App\Http\Requests\BentukUsahaRequest;
use App\Models\BentukUsaha;

class BentukUsahaController extends Controller
{
    public function list()
    {
        $data = BentukUsaha::whereNull('deleted_at')->orderBy('nama')->get();

        return $this->successResponse($data);
    }

    public function view($id)
    {
        $data = BentukUsaha::find($id);
        if (! $data) {
            return $this->notFoundResponse();
        }

        return $this->successResponse($data);
    }

    public function save(BentukUsahaRequest $request)
    {
        $data = BentukUsaha::create(['nama' => $request->nama]);

        return $this->successResponse($data, 'Berhasil ditambahkan', 201);
    }

    public function update(BentukUsahaRequest $request, $id)
    {
        $data = BentukUsaha::find($id);
        if (! $data) {
            return $this->notFoundResponse();
        }

        $data->update(['nama' => $request->nama]);

        return $this->successResponse($data, 'Berhasil diperbarui');
    }

    public function delete($id)
    {
        $data = BentukUsaha::find($id);
        if (! $data) {
            return $this->notFoundResponse();
        }

        $data->delete();

        return $this->messageResponse('Berhasil dihapus');
    }
}
