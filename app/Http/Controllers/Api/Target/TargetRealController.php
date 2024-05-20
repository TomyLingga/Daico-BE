<?php

namespace App\Http\Controllers\Api\Target;

use App\Http\Controllers\Controller;
use App\Models\MasterBulkProduksi;
use App\Models\MasterRetailProduksi;
use App\Models\TargetReal;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class TargetRealController extends Controller
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

            $existingEntry = TargetReal::where('tanggal', $request->tanggal)
                ->where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->first();

            if ($existingEntry) {
                return response()->json([
                    'message' => 'A TargetReal entry for this product and date already exists.',
                    'success' => false,
                ], 400);
            }

            $real = new TargetReal();
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
            $data = TargetReal::with('productable')->orderBy('tanggal')->get();

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

            // Fetch all records
            $data = TargetReal::with('productable')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('tanggal')
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            // Group the data by productable_id and productable_type and calculate totals
            $groupedData = $data->groupBy(function ($item) {
                return $item->productable_id . '-' . $item->productable_type;
            });

            $totals = $groupedData->map(function ($group) {
                $totalValue = $group->sum('value');
                $latestDate = $group->max('tanggal');
                $firstItem = $group->first();

                return [
                    'productable_id' => $firstItem->productable_id,
                    'productable_type' => $firstItem->productable_type,
                    'total' => $totalValue,
                    'latest_date' => $latestDate,
                    'productable' => $firstItem->productable,
                ];
            })->values();

            // Define the custom order for productable_type
            $customOrder = [
                "App\\Models\\MasterBulkProduksi" => 1,
                "App\\Models\\MasterRetailProduksi" => 2,
            ];

            // Sort the totals based on the custom order of productable_type
            $sortedTotals = $totals->sortBy(function ($item) use ($customOrder) {
                return $customOrder[$item['productable_type']] ?? 3;
            })->values();

            // Prepare the final response including both individual records and totals
            $response = [
                'data' => $data,
                'totals' => $sortedTotals,
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
            $data = TargetReal::with('productable')
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

            $existingEntry = TargetReal::where('tanggal', $request->tanggal)
                ->where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->first();

            if ($existingEntry && $existingEntry->id != $id) {
                return response()->json([
                    'message' => 'A TargetReal entry for this product and date already exists.',
                    'success' => false,
                ], 400);
            }

            $data = TargetReal::findOrFail($id);
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
