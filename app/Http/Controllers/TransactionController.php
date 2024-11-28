<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function index()
    {
        $transactions = Transaction::with('task')
            ->where('user_id', auth()->id())
            ->orderByDesc('created_at')
            ->get();

        return response()->json($transactions);
    }

    public function withdraw(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:1'
        ]);

        $user = auth()->user();

        if ($user->balance < $request->amount) {
            return response()->json(['message' => 'Insufficient balance'], 400);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amount' => $request->amount,
                'type' => 'withdrawal',
                'status' => 'pending'
            ]);

            $user->balance -= $request->amount;
            $user->save();

            Notification::create([
                'user_id' => $user->id,
                'title' => 'Withdrawal Requested',
                'message' => "Your withdrawal request for \${$request->amount} is being processed.",
                'type' => 'info',
                'is_read' => false
            ]);

            DB::commit();
            return response()->json($transaction);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to process withdrawal'], 500);
        }
    }
}