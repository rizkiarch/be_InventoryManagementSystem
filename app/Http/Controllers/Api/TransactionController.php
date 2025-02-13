<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends BaseController
{
    public function ProductIn(Request $request)
    {
        if (!$this->hasPermission('*.create')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        DB::beginTransaction();
        try {
            $data = $request->validate([
                'item_id' => 'required|exists:items,id',
                'qty' => 'required|integer|min:1',
                'type' => 'required|in:in,out',
                'status' => 'required|in:pending,success,cancelled'
            ]);

            $data['type'] = 'in';
            $data['status'] = 'success';

            $transaction = Transaction::create($data);

            $item = Item::find($request->item_id);
            $stock = Stock::updateOrCreate(
                ['item_id' => $item->id],
                [
                    'qty_in' => DB::raw('qty_in + ' . $request->qty),
                    'total' => DB::raw('total + ' . $request->qty)
                ]
            );

            DB::commit();

            return $this->successResponse($transaction, 'Transaction created successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan', $th->getMessage(), 400);
        }
    }

    public function ProductOut(Request $request)
    {
        if (!$this->hasPermission('*.create')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        DB::beginTransaction();
        try {
            $data = $request->validate([
                'item_id' => 'required|exists:items,id',
                'qty' => 'required|integer|min:1',
                'type' => 'required|in:in,out',
                'status' => 'required|in:pending,success,cancelled'
            ]);

            $data['type'] = 'in';
            $data['status'] = 'success';

            $item = Item::find($request->item_id);
            $stock = Stock::where('item_id', $item->id)->first();

            if (!$stock || $stock->total < $request->qty) {
                return response()->json(['message' => 'Stok tidak mencukupi'], 400);
            }

            $transaction = Transaction::create([
                'item_id' => $request->item_id,
                'qty' => $request->qty,
                'type' => 'out',
                'status' => $request->status
            ]);

            $stock->update([
                'qty_out' => DB::raw('qty_out + ' . $request->qty),
                'total' => DB::raw('total - ' . $request->qty)
            ]);

            DB::commit();

            return $this->successResponse($transaction, 'Transaction created successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan', $th->getMessage(), 400);
        }
    }

    public function index()
    {
        try {
            if (!$this->hasPermission('*.read')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $transactions = Transaction::with('item')->get();

            return $this->successResponse($transactions, 'Success');
        } catch (\Throwable $th) {
            return $this->errorResponse('Error', $th->getMessage(), 400);
        }
    }

    public function show($id)
    {
        try {
            if (!$this->hasPermission('*.read')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $transaction = Transaction::with('item')->find($id);

            if (!$transaction) {
                return $this->notFoundResponse('Transaction not found');
            }

            return $this->successResponse($transaction, 'Success');
        } catch (\Throwable $th) {
            return $this->errorResponse('Error', $th->getMessage(), 400);
        }
    }
}
