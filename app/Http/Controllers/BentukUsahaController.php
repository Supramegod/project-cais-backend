<?php

namespace App\Http\Controllers;

use App\Models\BentukUsaha;
use Illuminate\Http\Request;

class BentukUsahaController extends Controller
{
    public function list()
    {
        $data = BentukUsaha::whereNull('deleted_at')->orderBy('nama')->get();
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function view($id)
    {
        $data = BentukUsaha::find($id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }
        return response()->json(['success' => true, 'data' => $data]);
    }

    public function save(Request $request)
    {
        $request->validate(['nama' => 'required|string|max:100']);

        $data = BentukUsaha::create(['nama' => $request->nama]);
        return response()->json(['success' => true, 'message' => 'Berhasil ditambahkan', 'data' => $data], 201);
    }

    public function update(Request $request, $id)
    {
        $request->validate(['nama' => 'required|string|max:100']);

        $data = BentukUsaha::find($id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $data->update(['nama' => $request->nama]);
        return response()->json(['success' => true, 'message' => 'Berhasil diperbarui', 'data' => $data]);
    }

    public function delete($id)
    {
        $data = BentukUsaha::find($id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan'], 404);
        }

        $data->delete();
        return response()->json(['success' => true, 'message' => 'Berhasil dihapus']);
    }
}
