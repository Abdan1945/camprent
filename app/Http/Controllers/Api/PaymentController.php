<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    // PROSES PEMBAYARAN / UPLOAD BUKTI
    public function store(Request $request)
    {
        try {
            // Validasi data yang masuk dari frontend
            $request->validate([
                'rental_id'      => 'required|exists:rentals,id',
                'payment_method' => 'required|string',
                'amount'         => 'required|numeric',
                'payment_proof'  => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
            ]);

            // Cari data rental berdasarkan ID
            $rental = Rental::findOrFail($request->rental_id);

            // Simpan file bukti transfer ke storage/app/public/payments
            $path = null;
            if ($request->hasFile('payment_proof')) {
                $path = $request->file('payment_proof')->store('payments', 'public');
            }

            // Buat record pembayaran baru (status 'verified' sesuai enum tabel payments)
            $payment = Payment::create([
                'rental_id'      => $rental->id,
                'payment_code'   => 'PAY-' . strtoupper(Str::random(8)),
                'amount'         => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_proof'  => $path,
                'status'         => 'verified',
                'paid_at'        => now(),
            ]);

            // Update status pembayaran dan status sewa di tabel rentals
            // Menggunakan 'ready_for_pickup' yang valid sesuai enum migrasi rentals
            $rental->update([
                'payment_status' => 'paid',
                'rental_status'  => 'ready_for_pickup',
            ]);

            return response()->json([
                'message' => 'Pembayaran berhasil dikonfirmasi',
                'data'    => $payment
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal!',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan pada server backend!',
                'error'   => $e->getMessage(),
                'line'    => $e->getLine(),
                'file'    => $e->getFile()
            ], 500);
        }
    }

    // DETAIL PEMBAYARAN
    public function show($id)
    {
        $payment = Payment::with('rental')->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan'], 404);
        }

        return response()->json(['data' => $payment], 200);
    }
}
