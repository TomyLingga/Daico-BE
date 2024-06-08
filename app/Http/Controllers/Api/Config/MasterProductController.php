<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\MasterBulky;
use App\Models\MasterProduct;
use App\Models\MasterRetail;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterProductController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'productable_id' => 'required|integer',
                'product_type' => 'required|string|in:bulk,retail',
                'nama' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $productableType = null;
            if ($request->product_type === 'bulk') {
                MasterBulky::findOrFail($request->productable_id);
                $productableType = MasterBulky::class;
            } else if ($request->product_type === 'retail') {
                MasterRetail::findOrFail($request->productable_id);
                $productableType = MasterRetail::class;
            }


            $real = new MasterProduct();
            $real->nama = $request->nama;
            $real->productable_id = $request->productable_id;
            $real->productable_type = $productableType;

            $real->save();

            LoggerService::logAction($this->userData, $real, 'create', null, $real->toArray());

            DB::commit();

            return response()->json([
                'data' => $real,
                'message' => $this->messageCreate,
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function index()
    {
        try {
            $data = MasterProduct::with('productable','subProduct')
                        ->orderBy('productable_type')
                        ->orderBy('productable_id')
                        ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $data = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'productable_id' => $item->productable_id,
                    'productable_type' => $item->productable_type,
                    'productable' => $item->productable,
                    'nama' => $item->nama,
                    'sub_product' => $item->subProduct,
                ];
            });

            return response()->json(['data' => $data, 'message' => $this->messageAll], 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                // 'code' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $item = MasterProduct::with('productable','subProduct')
                                ->findOrFail($id);

            // $data['history'] = $this->formatLogs($data->logs);
            // unset($data->logs);

            $data = [
                'id' => $item->id,
                'productable_id' => $item->productable_id,
                'productable_type' => $item->productable_type,
                'productable' => $item->productable,
                'nama' => $item->nama,
                'sub_product' => $item->subProduct,
            ];

            return response()->json([
                'data' => $data,
                'message' => $this->messageSuccess,
                'code' => 200
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                // 'code' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function showProductable($id, $type)
    {
        try {
            $productableType = null;
            if ($type === 'bulk') {
                $productableType = MasterBulky::class;
            } else if ($type === 'retail') {
                $productableType = MasterRetail::class;
            }

            $data = MasterProduct::with('productable', 'subProduct')
                ->where('productable_id', $id)
                ->where('productable_type', $productableType)
                ->get();

            if (!$data) {
                return response()->json([
                    'message' => 'Item not found',
                    'success' => false,
                ], 404);
            }

            $data = $data->map(function ($item) {
                return [
                    'id' => $item->id,
                    'productable_id' => $item->productable_id,
                    'productable_type' => $item->productable_type,
                    'productable' => $item->productable,
                    'nama' => $item->nama,
                    'sub_product' => $item->subProduct,
                ];
            });

            return response()->json([
                'data' => $data,
                'message' => $this->messageSuccess,
                'code' => 200
            ], 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'productable_id' => 'required|integer',
                'product_type' => 'required|string|in:bulk,retail',
                'nama' => 'required'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $productableType = null;
            if ($request->product_type === 'bulk') {
                MasterBulky::findOrFail($request->productable_id);
                $productableType = MasterBulky::class;
            } else if ($request->product_type === 'retail') {
                MasterRetail::findOrFail($request->productable_id);
                $productableType = MasterRetail::class;
            }

            $data = MasterProduct::findOrFail($id);
            $oldData = $data->toArray();

            $data->productable_id = $request->productable_id;
            $data->productable_type = $productableType;
            $data->nama = $request->nama;
            $data->save();

            LoggerService::logAction($this->userData, $data, 'update', $oldData, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
                'message' => $this->messageUpdate,
                'success' => true,
            ], 200);

        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }
}
