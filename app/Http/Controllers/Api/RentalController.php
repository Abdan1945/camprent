<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use App\Models\Rental;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Rental::with(['user', 'rentalItems.equipment', 'payments']);

        if ($user->role !== 'admin') {
            $query->where('user_id', $user->id);
        }

        $rentals = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data'    => $rentals
        ], 200);
    }

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
                $equipment = Equipment::lockForUpdate()->findOrFail($item['equipment_id']);

                if ($equipment->stock < $item['qty']) {
                    DB::rollBack();
                    return response()->json([
                        'message' => "Stok peralatan {$equipment->name} tidak mencukupi"
                    ], 400);
                }

                $subtotal = $equipment->price_per_day * $item['qty'] * $days;
                $totalPrice += $subtotal;

                $rentalItemsData[] = [
                    'equipment'    => $equipment,
                    'equipment_id' => $equipment->id,
                    'qty'          => $item['qty'],
                    'subtotal'     => $subtotal,
                ];
            }

            // Simpan data rental
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
                $rental->rentalItems()->create([
                    'equipment_id' => $rentalItem['equipment_id'],
                    'qty'          => $rentalItem['qty'],
                    'subtotal'     => $rentalItem['subtotal'],
                ]);

                // Kurangi stok
                $rentalItem['equipment']->decrement('stock', $rentalItem['qty']);
            }

            $midtrans = app(MidtransService::class);
            $orderId = 'RENTAL-' . $rental->id . '-' . time();
            $snapToken = $midtrans->createSnapToken(
                $orderId,
                (float) $rental->total_price,
                [
                    'first_name' => $request->user()->name,
                    'email' => $request->user()->email,
                ]
            );

            DB::commit();

            return response()->json([
                'message'    => 'Pemesanan berhasil dibuat',
                'snap_token' => $snapToken,
                'data'       => $rental->load('rentalItems.equipment')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal memproses transaksi',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

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
            'data'    => $rental
        ], 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'rental_status'  => 'nullable|string',
            'payment_status' => 'nullable|string',
        ]);

        try {
            $rental = Rental::findOrFail($id);

            $rental->update(array_filter([
                'rental_status'  => $request->rental_status,
                'payment_status' => $request->payment_status,
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Status penyewaan berhasil diperbarui',
                'data'    => $rental
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui status: ' . $e->getMessage()
            ], 500);
        }
    }

    public function pickup(Request $request, $id)
    {
        $request->validate([
            'ktp_number'  => 'required|string|min:16|max:20',
            'pickup_notes' => 'nullable|string|max:1000',
        ]);

        try {
            $rental = Rental::findOrFail($id);

            if ($request->user()->id !== $rental->user_id && $request->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak berhak mengambil barang ini.'
                ], 403);
            }

            $rental->update([
                'ktp_number' => $request->ktp_number,
                'pickup_notes' => $request->pickup_notes ?? 'Barang diambil sesuai kondisi awal. KTP diserahkan sebagai jaminan.',
                'pickup_date' => now(),
                'rental_status' => 'ongoing',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil diambil dengan jaminan KTP.',
                'data' => $rental,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pengambilan barang: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function returnItem(Request $request, $id)
    {
        $request->validate([
            'return_notes' => 'nullable|string|max:1000',
            'is_damage' => 'nullable|boolean',
            'is_lost' => 'nullable|boolean',
            'damage_note' => 'nullable|string|max:1000',
            'lost_note' => 'nullable|string|max:1000',
        ]);

        try {
            $rental = Rental::findOrFail($id);

            $returnDate = Carbon::parse($request->return_date ?? now());
            $endDate = Carbon::parse($rental->end_date);
            $lateFee = 0;

            if ($returnDate->greaterThan($endDate)) {
                $lateFee = (float) $rental->total_price * 0.2;
            }

            $isDamage = $request->boolean('is_damage');
            $isLost = $request->boolean('is_lost');
            $status = 'completed';

            if ($isLost) {
                $status = 'lost';
            } elseif ($isDamage) {
                $status = 'damaged';
            }

            $rental->update([
                'return_notes' => $request->return_notes ?? 'Barang dikembalikan sesuai prosedur penyewaan.',
                'return_date' => $returnDate,
                'late_fee' => $lateFee,
                'is_damage' => $isDamage,
                'is_lost' => $isLost,
                'damage_note' => $request->damage_note,
                'lost_note' => $request->lost_note,
                'rental_status' => $status,
            ]);

            return response()->json([
                'success' => true,
                'message' => $isLost
                    ? 'Barang hilang tercatat. Silakan tindak lanjuti sesuai kesepakatan.'
                    : ($isDamage
                        ? 'Barang rusak tercatat. Silakan tindak lanjuti sesuai kesepakatan.'
                        : 'Barang berhasil dikembalikan.'),
                'late_fee' => $lateFee,
                'data' => $rental,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat pengembalian barang: ' . $e->getMessage(),
            ], 500);
        }
    }

    // public function uploadPayment(Request $request, $id)
    // {
    //     $request->validate([
    //         'payment_proof' => 'required|image|mimes:jpeg,png,jpg|max:2048',
    //     ]);

    //     try {
    //         $user = $request->user();
    //         $query = Rental::where('id', $id);

    //         if ($user->role !== 'admin') {
    //             $query->where('user_id', $user->id);
    //         }

    //         $rental = $query->firstOrFail();

    //         if ($request->hasFile('payment_proof')) {
    //             if ($rental->payment_proof) {
    //                 Storage::disk('public')->delete(str_replace('storage/', '', $rental->payment_proof));
    //             }

    //             $path = $request->file('payment_proof')->store('payment_proofs', 'public');

    //             $rental->payment_proof = $path;
    //             $rental->payment_status = 'paid';
    //             $rental->save();

    //             return response()->json([
    //                 'success' => true,
    //                 'message' => 'Bukti pembayaran berhasil diunggah',
    //                 'data'    => $rental->load(['user', 'rentalItems.equipment'])
    //             ], 200);
    //         }

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'File bukti pembayaran tidak ditemukan'
    //         ], 400);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Terjadi kesalahan: ' . $e->getMessage()
    //         ], 500);
    //     }
    // }
}
