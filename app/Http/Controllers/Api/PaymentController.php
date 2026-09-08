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
        $request->validate([
            'rental_id'      => 'required|exists:rentals,id',
            'payment_method' => 'required|string',
            'amount'         => 'required|numeric',
            'payment_proof'  => 'nullable|string',
        ]);

        $rental = Rental::findOrFail($request->rental_id);

        // Buat record pembayaran
        $payment = Payment::create([
            'rental_id'      => $rental->id,
            'payment_code'   => 'PAY-' . strtoupper(Str::random(8)),
            'amount'         => $request->amount,
            'payment_method' => $request->payment_method,
            'payment_proof'  => $request->payment_proof,
            'status'         => 'success',
            'paid_at'        => now(),
        ]);

        // Update status di tabel rentals
        $rental->update([
            'payment_status' => 'paid',
            'rental_status'  => 'approved',
        ]);

        return response()->json([
            'message' => 'Pembayaran berhasil dikonfirmasi',
            'data'    => $payment
        ], 201);
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
