<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    // READ ALL
    public function index()
    {
        $data = Equipment::with('category')->latest()->get();
        return response()->json(['success' => true, 'data' => $data], 200);
    }

    // CREATE + VALIDATION
    public function store(Request $request)
    {
        $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'name'         => 'required|string|max:255',
            'price_per_day'=> 'required|numeric',
            'stock'        => 'required|integer',
            'image'        => 'nullable|string',
            'description'  => 'nullable|string',
        ]);

        $equipment = Equipment::create($request->all());

        return response()->json([
            'message' => 'Equipment berhasil ditambahkan',
            'data'    => $equipment
        ], 201);
    }

    // READ SINGLE
    public function show($id)
    {
        $equipment = Equipment::with('category')->find($id);

        if (!$equipment) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        return response()->json(['data' => $equipment], 200);
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $equipment = Equipment::find($id);

        if (!$equipment) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $request->validate([
            'category_id'  => 'required|exists:categories,id',
            'name'         => 'required|string|max:255',
            'price_per_day'=> 'required|numeric',
            'stock'        => 'required|integer',
            'image'        => 'nullable|string',
            'description'  => 'nullable|string',
        ]);

        $equipment->update($request->all());

        return response()->json([
            'message' => 'Equipment berhasil diperbarui',
            'data'    => $equipment
        ], 200);
    }

    // DELETE
    public function destroy($id)
    {
        $equipment = Equipment::find($id);

        if (!$equipment) {
            return response()->json(['message' => 'Data tidak ditemukan'], 404);
        }

        $equipment->delete();

        return response()->json(['message' => 'Equipment berhasil dihapus'], 200);
    }
}
