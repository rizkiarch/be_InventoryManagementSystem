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

            $transaction = Transaction::create($data);

            $item = Item::find($request->item_id);
            if ($data['status'] === 'success') {
                $stock = Stock::updateOrCreate(
                    ['item_id' => $item->id],
                    [
                        'qty_in' => DB::raw('qty_in + ' . $request->qty),
                        'total' => DB::raw('total + ' . $request->qty)
                    ]
                );
            }

            $stock = Stock::updateOrCreate(
                ['item_id' => $item->id],
                [
                    'qty_in' => DB::raw('qty_in + ' . 0),
                    'total' => DB::raw('total + ' . 0)
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

            $data['type'] = 'out';

            $item = Item::find($request->item_id);
            $stock = Stock::where('item_id', $item->id)->first();
            if ($data['status'] === 'success') {

                if (!$stock || $stock->total < $request->qty) {
                    return response()->json(['message' => 'Stok tidak mencukupi'], 400);
                }

                $stock->update([
                    'qty_out' => DB::raw('qty_out + ' . $request->qty),
                    'total' => DB::raw('total - ' . $request->qty)
                ]);
            } else {
                if ($stock) {
                    $stock->update([
                        'qty_out' => DB::raw('qty_out + ' . 0),
                        'total' => DB::raw('total - ' . 0)
                    ]);
                } else {
                    return response()->json(['message' => 'Stock tidak ditemukan'], 400);
                }
            }

            $transaction = Transaction::create($data);

            DB::commit();

            return $this->successResponse($transaction, 'Transaction created successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan', $th->getMessage(), 400);
        }
    }

    public function updateProduct(Request $request, $id)
    {
        if (!$this->hasPermission('*.update')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::find($id);
            if (!$transaction) {
                return $this->notFoundResponse('Transaction not found');
            }
            $request->validate([
                'item_id' => 'nullable|exists:items,id',
                'qty' => 'nullable|integer|min:1',
                'type' => 'nullable|in:in,out',
                'status' => 'nullable|in:pending,success,cancelled'
            ]);

            $oldStatus = $transaction->status;
            $oldType = $transaction->type;
            $oldQty = $transaction->qty;

            $newStatus = $request->input('status', $transaction->status);
            $newType = $request->input('type', $transaction->type);
            $newQty = $request->input('qty', $transaction->qty);

            $item = Item::find($request->input('item_id', $transaction->item_id));
            $stock = Stock::where('item_id', $item->id)->first();

            if (!$stock) {
                return $this->errorResponse('Stock record not found for this item', 400);
            }

            if ($oldStatus === 'success') {
                if ($oldType === 'in') {
                    $stock->qty_in -= $oldQty;
                    $stock->total -= $oldQty;
                } else {
                    $stock->qty_out -= $oldQty;
                    $stock->total += $oldQty;
                }
            }

            if ($newStatus === 'success') {
                if ($newType === 'out' && $stock->total < $newQty) {
                    DB::rollBack();
                    return $this->errorResponse('Insufficient stock', 400);
                }

                if ($newType === 'in') {
                    $stock->qty_in += $newQty;
                    $stock->total += $newQty;
                } else {
                    $stock->qty_out += $newQty;
                    $stock->total -= $newQty;
                }
            }

            $transaction->update([
                'item_id' => $request->input('item_id', $transaction->item_id),
                'qty' => $newQty,
                'type' => $newType,
                'status' => $newStatus,
            ]);

            if ($oldStatus === 'success' || $newStatus === 'success') {
                $stock->save();
            }

            DB::commit();
            return $this->successResponse($transaction, 'Transaction created successfully');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan', $th->getMessage(), 400);
        }
    }

    public function approveTransaction($id)
    {
        if (!$this->hasPermission('*.update')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::find($id);
            if (!$transaction) {
                return $this->notFoundResponse('Transaction not found');
            }

            if ($transaction->status !== 'pending') {
                return $this->errorResponse('Only pending transactions can be marked as success', 400);
            }

            $item = Item::find($transaction->item_id);
            $stock = Stock::where('item_id', $item->id)->first();

            if (!$stock) {
                return $this->errorResponse('Stock record not found for this item', 400);
            }

            if ($transaction->type === 'out' && $stock->total < $transaction->qty) {
                return $this->errorResponse('Insufficient stock', 400);
            }

            if ($transaction->type === 'in') {
                $stock->update([
                    'qty_in' => DB::raw('qty_in + ' . $transaction->qty),
                    'total' => DB::raw('total + ' . $transaction->qty)
                ]);
            } else {
                $stock->update([
                    'qty_out' => DB::raw('qty_out + ' . $transaction->qty),
                    'total' => DB::raw('total - ' . $transaction->qty)
                ]);
            }

            $transaction->update(['status' => 'success']);

            DB::commit();
            return $this->successResponse($transaction, 'Transaction marked as success');
        } catch (\Throwable $th) {
            DB::rollBack();
            return $this->errorResponse('Terjadi kesalahan', $th->getMessage(), 400);
        }
    }

    public function cancelTransaction($id)
    {
        if (!$this->hasPermission('*.update')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        DB::beginTransaction();
        try {
            $transaction = Transaction::find($id);
            if (!$transaction) {
                return $this->notFoundResponse('Transaction not found');
            }

            if ($transaction->status === 'success') {
                $stock = Stock::where('item_id', $transaction->item_id)->first();

                if (!$stock) {
                    return $this->errorResponse('Stock record not found for this item', 400);
                }

                if ($transaction->type === 'in') {
                    $stock->update([
                        'qty_in' => DB::raw('qty_in - ' . $transaction->qty),
                        'total' => DB::raw('total - ' . $transaction->qty)
                    ]);
                } else { // type is 'out'
                    $stock->update([
                        'qty_out' => DB::raw('qty_out - ' . $transaction->qty),
                        'total' => DB::raw('total + ' . $transaction->qty)
                    ]);
                }
            }

            $transaction->update(['status' => 'cancelled']);

            DB::commit();
            return $this->successResponse($transaction, 'Transaction cancelled successfully');
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

            $perPage = request()->input('per_page', 5);
            $page = request()->input('page', 1);
            $search = request()->input('search', '');

            $query = Transaction::with('item', 'item.photos')->orderBy('created_at', 'desc');

            if (!empty($search)) {
                $query = $query->whereHas('item', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%");
                });
            }

            $transactions = $query->paginate($perPage, ['*'], 'page', $page);

            $transactionsIn = Transaction::where('type', 'in')->count();
            $transactionsOut = Transaction::where('type', 'out')->count();

            return $this->successResponse([
                'transactions' => $transactions,
                'transactionsIn' => $transactionsIn,
                'transactionsOut' => $transactionsOut
            ], 'Success');
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
