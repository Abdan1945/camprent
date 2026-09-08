<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Rental;
use App\Models\RentalItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RentalController extends Controller
{
    // GET ALL RENTALS (Daftar Transaksi)
    public function index(Request $request)
    {
        $user = $request->user();

        // Jika admin tampilkan semua, jika customer tampilkan miliknya saja
        $query = Rental::with(['user', 'rentalItems.equipment']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $rentals = $query->latest()->get();

        return response()->json(['success' => true, 'data' => $rentals], 200);
    }

    // CREATE RENTAL (Proses Checkout / Sewa)
    public function store(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date'   => 'required|date|after:start_date',
            'items'      => 'required|array|min:1',
            'items.*.equipment_id' => 'required|exists:equipments,id',
            'items.*.qty'          => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $totalPrice = 0;
            $rentalItemsData = [];

            // Hitung durasi sewa (hari)
            $startDate = \Carbon\Carbon::parse($request->start_date);
            $endDate = \Carbon\Carbon::parse($request->end_date);
            $days = $startDate::diffInDays($endDate) ?: 1;

            // Validasi stok dan hitung total harga
            foreach ($request->items as $item) {
                $equipment = Equipment::findOrFail($item['equipment_id']);

                if ($equipment->stock < $item['qty']) {
                    return response()->json([
                        'message' => "Stok {$equipment->name} tidak mencukupi"
                    ], 400);
                }

                $subtotal = $equipment->price_per_day * $item['qty'] * $days;
                $totalPrice += $subtotal;

                $rentalItemsData[] = [
                    'equipment_id' => $equipment->id,
                    'qty'          => $item['qty'],
                    'subtotal'     => $subtotal,
                ];

                // Kurangi stok barang
                $equipment->decrement('stock', $item['qty']);
            }

            // Simpan transaksi utama
            $rental = Rental::create([
                'user_id'       => $request->user()->id,
                'rental_code'   => 'RENT-' . strtoupper(Str::random(8)),
                'start_date'    => $request->start_date,
                'end_date'      => $request->end_date,
                'total_price'   => $totalPrice,
                'payment_status' => 'unpaid',
                'rental_status'  => 'pending',
            ]);

            // Simpan rincian item sewa
            foreach ($rentalItemsData as $rentalItem) {
                $rental->rentalItems()->create($rentalItem);
            }

            DB::commit();

            return response()->json([
                'message' => 'Pemesanan berhasil dibuat',
                'data'    => $rental->load('rentalItems.equipment')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memproses transaksi', 'error' => $e->getMessage()], 500);
        }
    }

    // GET DETAIL RENTAL
    public function show($id)
    {
        $rental = Rental::with(['user', 'rentalItems.equipment', 'payment'])->find($id);

        if (!$rental) {
            return response()->json(['message' => 'Transaksi tidak ditemukan'], 404);
        }

        return response()->json(['data' => $rental], 200);
    }
}
