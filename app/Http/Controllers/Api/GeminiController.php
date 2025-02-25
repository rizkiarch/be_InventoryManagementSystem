<?php

namespace App\Http\Controllers\Api;

use App\Models\GeminiResult;
use Illuminate\Http\Request;
use App\Http\Controllers\BaseController;
use App\Models\Item;
use App\Models\Transaction;
use Carbon\Carbon;

class GeminiController extends BaseController
{
    public function __construct() {}

    public function getGeminiResults(Request $request)
    {
        if (!$this->hasPermission('*.read')) {
            return $this->errorResponse('Unauthorized', 403);
        }

        try {
            $request->validate([
                'page' => 'nullable|integer|min:1',
                'per_page' => 'nullable|integer|min:1',
                'search' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $perPage = $request->input('per_page', Transaction::where('status', 'success')->count());
            $search = $request->input('search', '');

            $startDate = $request->input('start_date') ?? Carbon::now()
                ->startOfMonth()
                ->toDateString();
            $endDate = $request->input('end_date') ?? Carbon::now()
                ->endOfMonth()
                ->toDateString();

            $query = GeminiResult::whereBetween('created_at', [$startDate, $endDate])
                ->when($search, function ($query) use ($search) {
                    return $query->where('prompt', 'like', '%' . $search . '%')
                        ->orWhere('response', 'like', '%' . $search . '%');
                })
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->successResponse($query, 'Gemini results retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorResponse('', $th->getMessage(), 400);
        }
    }

    public function show(GeminiResult $result)
    {
        return response()->json($result);
    }

    public function latest()
    {
        // $search = '';

        // $startDate = Carbon::now()
        //     ->startOfMonth();


        // $endDate = Carbon::now();


        // $queryTransaction = Transaction::with('item')
        //     ->whereBetween('updated_at', [$startDate, $endDate])
        //     ->where('status', 'success')
        //     // ->when($search, function ($query) use ($search) {
        //     //     return $query->whereHas('item', function ($query) use ($search) {
        //     //         $query->where('name', 'like', '%' . $search . '%')
        //     //             ->orWhere('unique_code', 'like', '%' . $search . '%');
        //     //     });
        //     // })
        //     ->orderBy('updated_at', 'desc')
        //     ->get();

        // $totalProductIn = (clone $queryTransaction)->where('type', 'in')->sum('qty');
        // $totalProductOut = (clone $queryTransaction)->where('type', 'out')->sum('qty');
        // $productManyStock = $this->getManyStock();
        // $formatProductStock = $this->formatProductStock($productManyStock);

        // $topProducts = $this->getTopProducts();
        // $formatTopProducts = $this->formatTopProducts();
        // return response()->json([
        //     'start_date' => $startDate,
        //     'end_date' => $endDate,
        //     'total_product_in' => $totalProductIn,
        //     'total_product_out' => $totalProductOut,
        //     'product_many_stock' => $formatProductStock,
        //     'top_products' => $formatTopProducts,
        // ]);
        $result = GeminiResult::where('status', 'completed')
            ->latest()
            ->first();

        if (!$result) {
            return $this->errorResponse('', $th->getMessage(), 400);
        }

        return $this->successResponse($result, 'Latest Gemini result retrieved successfully');
    }

    public function promptGemini()
    {
        $search = '';

        $startDate = Carbon::now()
            ->startOfMonth();


        $endDate = Carbon::now();


        $queryTransaction = Transaction::with('item')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->where('status', 'success')
            // ->when($search, function ($query) use ($search) {
            //     return $query->whereHas('item', function ($query) use ($search) {
            //         $query->where('name', 'like', '%' . $search . '%')
            //             ->orWhere('unique_code', 'like', '%' . $search . '%');
            //     });
            // })
            ->orderBy('updated_at', 'desc')
            ->get();

        $totalProductIn = (clone $queryTransaction)->where('type', 'in')->sum('qty');
        $totalProductOut = (clone $queryTransaction)->where('type', 'out')->sum('qty');
        $productManyStock = $this->getManyStock();
        $formatProductStock = $this->formatProductStock($productManyStock);

        $topProducts = $this->getTopProducts();
        $formatTopProducts = $this->formatTopProducts();
        return response()->json([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_product_in' => $totalProductIn,
            'total_product_out' => $totalProductOut,
            'product_many_stock' => $formatProductStock,
            'top_products' => $formatTopProducts,
        ]);
    }

    private function getManyStock()
    {
        $manyStock = Item::with('stocks')
            ->whereHas('stocks', function ($query) {
                $query->havingRaw('(qty_in - qty_out) > ?', [50]); // batas minimum selisih yang diinginkan
            })
            ->withSum('stocks as qty_in', 'qty_in')
            ->withSum('stocks as qty_out', 'qty_out')
            ->withSum('stocks as total_stock', 'total')
            ->orderByDesc('total_stock')
            ->get();

        $getName = $manyStock->map(function ($item) {
            return [
                'name' => $item->name,
                'qty_in' => $item->qty_in,
                'qty_out' => $item->qty_out,
                'total_stock' => $item->total_stock,
            ];
        })->toArray();

        return $getName;
    }

    private function formatProductStock($productStocks)
    {
        if (empty($productStocks)) {
            return 'Tidak ada data';
        }

        return collect($productStocks)->map(function ($item) {
            return "{$item['name']} (Stok: {$item['total_stock']} | Masuk: {$item['qty_in']} | Keluar: {$item['qty_out']})";
        })->implode("\n");
    }

    private function getTopProducts()
    {
        $transactions = Transaction::with('item')->get();

        $products = collect($transactions)->groupBy('item_id')->map(function ($group) {
            $item = $group->first()->item;
            return [
                'name' => $item->name,
                'in' => $group->where('type', 'in')->sum('qty'),
                'out' => $group->where('type', 'out')->sum('qty'),
            ];
        });

        $sortedProducts = $products->sortByDesc(function ($product) {
            return $product['in'] + $product['out'];
        });

        $topProducts = $sortedProducts->take(5);
        return $topProducts;
    }

    private function formatTopProducts()
    {
        $topProducts = $this->getTopProducts();

        if ($topProducts->isEmpty()) {
            return 'Tidak ada data';
        }

        return $topProducts->map(function ($product) {
            return "{$product['name']} (Masuk: {$product['in']} | Keluar: {$product['out']})";
        })->implode("\n");
    }
}
