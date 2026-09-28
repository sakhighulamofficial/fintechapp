<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferService {
    public function send(int $senderId, int $recipientId, int $amount, string $requestId): object {
        return DB::transaction(function () use ($senderId, $recipientId, $amount, $requestId) {
            // The unique request ID is checked again inside the transaction. Never retry a failed key as a new payment.
            $prior = DB::table('transfers')->where('request_id', $requestId)->first();
            if ($prior) {
                if ($prior->sender_id !== $senderId || $prior->recipient_id !== $recipientId || $prior->amount_paisa !== $amount) {
                    throw ValidationException::withMessages(['amount' => 'This request ID was used for a different transfer.']);
                }
                return $prior;
            }
            if ($senderId === $recipientId || $amount < 1) {
                throw ValidationException::withMessages(['amount' => 'Invalid transfer.']);
            }
            // Conditional debit is atomic even with SQLite's serialized writers; a competing debit cannot overdraw.
            $debited = DB::table('wallets')->where('user_id', $senderId)->where('balance_paisa', '>=', $amount)->decrement('balance_paisa', $amount);
            if ($debited !== 1) throw ValidationException::withMessages(['amount' => 'Insufficient funds.']);
            $credited = DB::table('wallets')->where('user_id', $recipientId)->increment('balance_paisa', $amount);
            if ($credited !== 1) throw ValidationException::withMessages(['recipient_id' => 'Recipient does not exist.']);
            $id = DB::table('transfers')->insertGetId([
                'sender_id' => $senderId, 'recipient_id' => $recipientId, 'amount_paisa' => $amount,
                'request_id' => $requestId, 'status' => 'completed', 'created_at' => now(), 'updated_at' => now(),
            ]);
            return DB::table('transfers')->find($id);
        }, 3);
    }
}
