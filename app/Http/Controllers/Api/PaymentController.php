<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Midtrans\Notification;

class PaymentController extends Controller
{
    private function initMidtransConfig(): void
    {
        app(MidtransService::class)->configure();
    }

    public function getSnapToken(Request $request)
    {
        try {
            $request->validate([
                'rental_id' => 'required|exists:rentals,id',
            ]);

            $rental = Rental::with('user')->findOrFail($request->rental_id);

            // Ambil user dari relasi atau dari request auth
            $user = $rental->user ?? $request->user();

            $this->initMidtransConfig();

            $orderId = 'RENTAL-' . $rental->id . '-' . time();

            $midtrans = app(MidtransService::class);
            $snapToken = $midtrans->createSnapToken(
                $orderId,
                (float) $rental->total_price,
                [
                    'first_name' => $user->name ?? 'Customer',
                    'email' => $user->email ?? 'customer@example.com',
                ]
            );

            return response()->json([
                'success'    => true,
                'message'    => 'Snap token berhasil didapatkan',
                'snap_token' => $snapToken
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Validasi gagal!',
                'errors'  => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mendapatkan token Midtrans!',
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'rental_id'      => 'required|exists:rentals,id',
                'payment_method' => 'required|string',
                'amount'         => 'required|numeric',
                'payment_proof'  => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
            ]);

            $rental = Rental::findOrFail($request->rental_id);

            $path = null;
            if ($request->hasFile('payment_proof')) {
                $path = $request->file('payment_proof')->store('payments', 'public');
            }

            $payment = Payment::create([
                'rental_id'      => $rental->id,
                'payment_code'   => 'PAY-' . strtoupper(Str::random(8)),
                'amount'         => $request->amount,
                'payment_method' => $request->payment_method,
                'payment_proof'  => $path,
                'status'         => 'verified',
                'paid_at'        => now(),
            ]);

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
                'error'   => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $payment = Payment::with('rental')->find($id);

        if (!$payment) {
            return response()->json(['message' => 'Data pembayaran tidak ditemukan'], 404);
        }

        return response()->json(['data' => $payment], 200);
    }

    public function callback(Request $request)
    {
        try {
            $this->initMidtransConfig();

            $notif = new Notification();

            $transactionStatus = $notif->transaction_status;
            $orderId = $notif->order_id;
            $type = $notif->payment_type ?? 'unknown';
            $fraud = $notif->fraud_status ?? null;

            $rentalId = null;
            if (preg_match('/RENTAL-(\d+)/', $orderId, $matches)) {
                $rentalId = (int) $matches[1];
            }

            $rental = Rental::with('rentalItems.equipment')->find($rentalId);

            if (!$rental) {
                return response()->json(['message' => 'Rental tidak ditemukan'], 404);
            }

            $paymentData = [
                'rental_id' => $rental->id,
                'payment_code' => $orderId,
                'amount' => (float) ($notif->gross_amount ?? $rental->total_price),
                'payment_method' => $type,
                'status' => 'pending',
                'paid_at' => null,
            ];

            if ($transactionStatus == 'capture') {
                if ($type == 'credit_card') {
                    if ($fraud == 'challenge') {
                        $rental->update(['payment_status' => 'pending']);
                    } else {
                        $rental->update([
                            'payment_status' => 'paid',
                            'rental_status' => 'ready_for_pickup',
                        ]);
                        $paymentData['status'] = 'verified';
                        $paymentData['paid_at'] = now();
                    }
                }
            } elseif ($transactionStatus == 'settlement') {
                $rental->update([
                    'payment_status' => 'paid',
                    'rental_status' => 'ready_for_pickup',
                ]);
                $paymentData['status'] = 'verified';
                $paymentData['paid_at'] = now();
            } elseif ($transactionStatus == 'pending') {
                $rental->update(['payment_status' => 'pending']);
                $paymentData['status'] = 'pending';
            } elseif (in_array($transactionStatus, ['deny', 'expire', 'cancel'], true)) {
                if ($rental->rental_status !== 'cancelled') {
                    foreach ($rental->rentalItems as $item) {
                        if ($item->equipment) {
                            $item->equipment->increment('stock', $item->qty);
                        }
                    }
                }

                $rental->update([
                    'payment_status' => 'failed',
                    'rental_status' => 'cancelled',
                ]);
                $paymentData['status'] = 'rejected';
            }

            Payment::updateOrCreate(
                ['rental_id' => $rental->id],
                $paymentData
            );

            return response()->json(['message' => 'Callback Midtrans berhasil diproses'], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan pada webhook Midtrans!',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
