<?php

namespace App\Http\Controllers\Api\Target;

use App\Http\Controllers\Controller;
use App\Models\MasterBulkProduksi;
use App\Models\MasterRetailProduksi;
use App\Models\TargetRKAP;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TargetRkapController extends Controller
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
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $productableType = null;
            if ($request->product_type === 'bulk') {
                MasterBulkProduksi::findOrFail($request->productable_id);
                $productableType = MasterBulkProduksi::class;
            } else if ($request->product_type === 'retail') {
                MasterRetailProduksi::findOrFail($request->productable_id);
                $productableType = MasterRetailProduksi::class;
            }

            $existingEntry = TargetRKAP::whereYear('tanggal', '=', date('Y', strtotime($request->tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($request->tanggal)))
                ->where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->first();

            if ($existingEntry) {
                return response()->json([
                    'message' => 'A TargetRKAP entry for this product and month already exists.',
                    'success' => false,
                ], 400);
            }

            $real = new TargetRKAP();
            $real->tanggal = $request->tanggal;
            $real->value = $request->value;
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
            $data = TargetRKAP::with('productable')->orderBy('tanggal')->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

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

    public function indexDate(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $data = TargetRKAP::with('productable')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('productable_type', 'desc')
                ->orderBy('productable_id', 'asc')
                ->orderBy('tanggal', 'asc')
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $response = [
                'data' => $data,
                'message' => $this->messageAll,
            ];

            return response()->json($response, 200);

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
            $data = TargetRKAP::with('productable')
                                ->findOrFail($id);

            // $data['history'] = $this->formatLogs($data->logs);
            // unset($data->logs);


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
                'product_type' => 'required|string|in:bulk,retail',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $productableType = null;
            if ($request->product_type === 'bulk') {
                MasterBulkProduksi::findOrFail($request->productable_id);
                $productableType = MasterBulkProduksi::class;
            } else if ($request->product_type === 'retail') {
                MasterRetailProduksi::findOrFail($request->productable_id);
                $productableType = MasterRetailProduksi::class;
            }

            $existingEntry = TargetRKAP::whereYear('tanggal', '=', date('Y', strtotime($request->tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($request->tanggal)))
                ->where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->first();

            if ($existingEntry && $existingEntry->id != $id) {
                return response()->json([
                    'message' => 'A TargetReal entry for this product and date already exists.',
                    'success' => false,
                ], 400);
            }

            $data = TargetRKAP::findOrFail($id);
            $oldData = $data->toArray();

            $data->productable_id = $request->productable_id;
            $data->productable_type = $productableType;
            $data->tanggal = $request->tanggal;
            $data->value = $request->value;
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
