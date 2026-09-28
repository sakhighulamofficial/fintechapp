<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatementController extends Controller
{
    public function statement(Request $request): mixed
    {
        $transactions = DB::table('transfers as t')->join('users as s', 's.id', '=', 't.sender_id')->join('users as r', 'r.id', '=', 't.recipient_id')
            ->where(fn ($query) => $query->where('t.sender_id', $request->user()->id)->orWhere('t.recipient_id', $request->user()->id))
            ->select('t.*', 's.name as sender_name', 'r.name as recipient_name')->orderByDesc('t.id')->paginate(20);

        return view('statement', compact('transactions'));
    }

    public function receipt(Request $request, int $transfer): mixed
    {
        $record = DB::table('transfers as t')->join('users as s', 's.id', '=', 't.sender_id')->join('users as r', 'r.id', '=', 't.recipient_id')
            ->where('t.id', $transfer)->where(fn ($query) => $query->where('t.sender_id', $request->user()->id)->orWhere('t.recipient_id', $request->user()->id))
            ->select('t.*', 's.name as sender_name', 'r.name as recipient_name')->first();
        abort_unless($record, 404);

        return view('receipt', compact('record'));
    }
}
