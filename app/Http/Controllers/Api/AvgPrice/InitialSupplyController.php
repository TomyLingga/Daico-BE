<?php

namespace App\Http\Controllers\Api\AvgPrice;

use App\Http\Controllers\Controller;
use App\Models\InitialSupply;
use App\Models\MasterBulky;
use App\Models\MasterProduct;
use App\Models\MasterSubProduct;
use App\Services\LoggerService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InitialSupplyController extends Controller
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
                'product_type' => 'required|string|in:bulk,product,subproduct',
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric',
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
            } else if ($request->product_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->product_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $tanggal = Carbon::parse($request->tanggal);
            $month = $tanggal->format('m');
            $year = $tanggal->format('Y');

            $existingRecord = InitialSupply::where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->whereMonth('tanggal', $month)
                ->whereYear('tanggal', $year)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'A record already exists for this productable in the given month.',
                    'success' => false,
                ], 400);
            }

            $real = new InitialSupply();
            $real->productable_id = $request->productable_id;
            $real->productable_type = $productableType;
            $real->tanggal = $request->tanggal;
            $real->qty = $request->qty;
            $real->harga = $request->harga;

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

    public function indexDate(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $data = InitialSupply::with('productable')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
            });

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data, 'message' => $this->messageAll], 200);
        } catch (QueryException $e) {
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
            $data = InitialSupply::with('productable')->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
            });

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data, 'message' => $this->messageAll], 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $data = InitialSupply::with('productable')
                                ->findOrFail($id);
            $data->makeHidden('productable');
            $data->extended_productable;
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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'productable_id' => 'required|integer',
                'product_type' => 'required|string|in:bulk,product,subproduct',
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric',
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
            } else if ($request->product_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->product_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $data = InitialSupply::findOrFail($id);
            $oldData = $data->toArray();

            $tanggal = Carbon::parse($request->tanggal);
            $month = $tanggal->format('m');
            $year = $tanggal->format('Y');

            $existingRecord = InitialSupply::where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->whereMonth('tanggal', $month)
                ->whereYear('tanggal', $year)
                ->where('id', '!=', $id)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'A record already exists for this productable in the given month.',
                    'success' => false,
                ], 400);
            }

            $data->productable_id = $request->productable_id;
            $data->productable_type = $productableType;
            $data->tanggal = $request->tanggal;
            $data->qty = $request->qty;
            $data->harga = $request->harga;
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
