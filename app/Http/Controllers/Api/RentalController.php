<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RentalController extends Controller
{
    // Mengambil daftar rental (Otomatis membedakan Admin & User)
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Rental::with(['user', 'rentalItems.equipment', 'payments']);

        // Jika bukan admin, hanya ambil data milik user yang sedang login
        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $rentals = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $rentals
        ], 200);
    }

    // Proses pemesanan / checkout alat camping
    public function store(Request $request)
    {
        $request->validate([
            'start_date'           => 'required|date|after_or_equal:today',
            'end_date'             => 'required|date|after:start_date',
            'items'                => 'required|array|min:1',
            'items.*.equipment_id' => 'required|exists:equipments,id',
            'items.*.qty'          => 'required|integer|min:1',
        ]);

        DB::beginTransaction();
        try {
            $totalPrice = 0;
            $rentalItemsData = [];

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);
            $days = $startDate->diffInDays($endDate) ?: 1;

            foreach ($request->items as $item) {
                $equipment = Equipment::findOrFail($item['equipment_id']);

                if ($equipment->stock < $item['qty']) {
                    return response()->json([
                        'message' => "Stok peralatan {$equipment->name} tidak mencukupi"
                    ], 400);
                }

                $subtotal = $equipment->price_per_day * $item['qty'] * $days;
                $totalPrice += $subtotal;

                $rentalItemsData[] = [
                    'equipment_id' => $equipment->id,
                    'qty'          => $item['qty'],
                    'subtotal'     => $subtotal,
                ];

                $equipment->decrement('stock', $item['qty']);
            }

            $rental = Rental::create([
                'user_id'        => $request->user()->id,
                'rental_code'    => 'RENT-' . strtoupper(Str::random(8)),
                'start_date'     => $request->start_date,
                'end_date'       => $request->end_date,
                'total_price'    => $totalPrice,
                'payment_status' => 'unpaid',
                'rental_status'  => 'pending',
            ]);

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
            return response()->json([
                'message' => 'Gagal memproses transaksi',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    // Menampilkan detail transaksi berdasarkan ID
    public function show($id)
    {
        $rental = Rental::with(['user', 'rentalItems.equipment', 'payments'])->find($id);

        if (!$rental) {
            return response()->json([
                'message' => 'Transaksi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $rental
        ], 200);
    }

    // Mengunggah bukti pembayaran sesuai rute /rentals/{id}/payment
    public function uploadPayment(Request $request, $id)
    {
        $request->validate([
            'payment_proof' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        try {
            $user = $request->user();
            $query = Rental::where('id', $id);

            if ($user->role !== 'admin') {
                $query->where('user_id', $user->id);
            }

            $rental = $query->firstOrFail();

            if ($request->hasFile('payment_proof')) {
                if ($rental->payment_proof) {
                    Storage::disk('public')->delete(str_replace('storage/', '', $rental->payment_proof));
                }

                $path = $request->file('payment_proof')->store('payment_proofs', 'public');

                $rental->payment_proof = $path;
                $rental->payment_status = 'paid';
                $rental->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Bukti pembayaran berhasil diunggah',
                    'data'    => $rental->load(['user', 'rentalItems.equipment'])
                ], 200);
            }

            return response()->json([
                'success' => false,
                'message' => 'File bukti pembayaran tidak ditemukan'
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
