<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseController;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Stock;
use Haruncpi\LaravelIdGenerator\IdGenerator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProductController extends BaseController
{
    public function __construct() {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            if (!$this->hasPermission('*.read')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $perPage = $request->input('per_page', 5);
            $page = $request->input('page', 1);
            $search = $request->input('search', '');

            $query = Item::with('photos', 'transactions');

            $products = $query->when(!empty($search), function ($q) use ($search) {
                return $q->where('name', 'like', "%{$search}%")
                    ->orWhere('unique_code', 'like', "%{$search}%");
            })->paginate($perPage, ['*'], 'page', $page);

            foreach ($products as $product) {
                $product->total_stock = Stock::where('item_id', $product->id)->sum('total');
            }

            return $this->successResponse($products, 'Products retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorResponse('', $th->getMessage(), 400);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            if (!$this->hasPermission('*.create')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $data = $request->validate([
                'unique_code' => 'nullable|string|unique:items',
                'name' => 'required|string',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'is_delivery' => 'nullable|boolean',
                'photo' => 'nullable|array',
                'photo.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            // Set default values
            $data['is_active'] = $data['is_active'] ?? true;
            $data['is_delivery'] = $data['is_delivery'] ?? false;
            $data['unique_code'] = IdGenerator::generate([
                'table' => 'items',
                'field' => 'unique_code',
                'length' => 12,
                'reset_on_prefix_change' => true,
                'prefix' => date("ymd") . strtoupper(substr(uniqid(), -5)),
            ]);

            $product = Item::create($data);
            if ($request->hasFile('photos')) {
                $this->addOrUpdatePhotos($request, $product);
            }
            $product->load('photos');

            return $this->successResponse($product, 'Product Created', 201);
        } catch (\Throwable $th) {
            return $this->errorResponse('Product Failed to Create', $th->getMessage(), 400);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            if (!$this->hasPermission('*.read')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $product = Item::with('photos')->find($id);

            if (!$product) {
                return $this->notFoundResponse('Item not found');
            }

            $stock = Stock::all();
            $productStock = $stock->where('item_id', $product->id)->sum('total');
            $product->total_stock = $productStock;

            return $this->successResponse($product, 'Product retrieved successfully');
        } catch (\Throwable $th) {
            return $this->errorResponse('', $th->getMessage(), 400);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        try {
            if (!$this->hasPermission('*.update')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $product = Item::find($id);
            if (!$product) {
                return $this->notFoundResponse('Item not found');
            }

            $request->validate([
                'name' => 'nullable|string',
                'description' => 'nullable|string',
                'is_active' => 'nullable|boolean',
                'is_delivery' => 'nullable|boolean',
                'photos' => 'nullable|array',
                'photos.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
            ]);

            // Then prepare the data for update
            $data = [
                'name' => $request->input('name', $product->name),
                'description' => $request->input('description', $product->description),
                'is_active' => $request->input('is_active', $product->is_active),
                'is_delivery' => $request->input('is_delivery', $product->is_delivery),
            ];

            $data['unique_code'] = $request->input('unique_code', $product->unique_code);

            $product->update($data);

            if ($request->hasFile('photos')) {
                $this->addOrUpdatePhotos($request, $product);
            }
            $product->load('photos');

            return $this->successResponse($product, 'Product updated successfully');
        } catch (\Throwable $th) {
            return $this->errorResponse('Product update failed', $th->getMessage(), 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            if (!$this->hasPermission('*.delete')) {
                return $this->errorResponse('Unauthorized', 403);
            }

            $product = Item::find($id);
            if (!$product) {
                return $this->notFoundResponse('Product not found');
            }

            $product->delete();
            return $this->successResponse($product, 'Product deleted successfully');
        } catch (\Throwable $th) {
            return $this->errorResponse('', $th->getMessage(), 400);
        }
    }

    public function addOrUpdatePhotos(Request $request, $product)
    {
        try {
            $item = Item::find($product->id);
            if (!$item) {
                return $this->notFoundResponse('Item not found');
            }

            $oldPhotos = ItemPhoto::where('item_id', $product->id)->get();
            foreach ($oldPhotos as $oldPhoto) {
                if (Storage::disk('public')->exists($oldPhoto->photo)) {
                    Storage::disk('public')->delete($oldPhoto->photo);
                }
                $oldPhoto->delete();
            }

            $uploadedPhotos = [];

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $file) {
                    $filename = date("Ymd") . '_' . uniqid() . '_' . $product->name . '.' . $file->getClientOriginalExtension();
                    $path = $file->storeAs('uploads/product', $filename, 'public');

                    $photo = ItemPhoto::create([
                        'item_id' => $product->id,
                        'photo' => $path,
                    ]);

                    $uploadedPhotos[] = $photo;
                }
            }

            return $uploadedPhotos;
        } catch (\Throwable $th) {
            throw $th;
        }
    }
}
