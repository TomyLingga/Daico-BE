<?php

namespace App\Http\Controllers\Api\CPO;

use App\Http\Controllers\Controller;
use App\Models\actualIncomingCpo;
use App\Models\StockAwalCpo;
use App\Services\LoggerService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockAwalCpoController extends Controller
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
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $existingRecord = StockAwalCpo::whereYear('tanggal', '=', date('Y', strtotime($request->input('tanggal'))))
                ->whereMonth('tanggal', '=', date('m', strtotime($request->input('tanggal'))))
                ->exists();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'Data already exists for this month.',
                    'success' => false,
                ], 400);
            }

            $data = StockAwalCpo::create($request->all());

            LoggerService::logAction($this->userData, $data, 'create', null, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
                'message' => $this->messageCreate,
                // 'code' => 200,
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                // 'code' => 500,
                'success' => false,
            ], 500);
        }
    }

    public function index()
    {
        try {
            $data = StockAwalCpo::orderBy('tanggal')->get();

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
            $data = $this->processStockAwalCpo($request);

            return response()->json([
                'data' => $data,
                'message' => $this->messageAll,
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

    public function show($id)
    {
        try {
            $data = StockAwalCpo::findOrFail($id);

            $data['history'] = $this->formatLogs($data->logs);
            unset($data->logs);

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

            $rules = [
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $data = StockAwalCpo::findOrFail($id);

            $existingRecord = StockAwalCpo::whereYear('tanggal', '=', date('Y', strtotime($request->input('tanggal'))))
                ->whereMonth('tanggal', '=', date('m', strtotime($request->input('tanggal'))))
                ->where('id', '!=', $id)
                ->exists();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'Data already exists for this month.',
                    'success' => false,
                ], 400);
            }

            $oldData = $data->toArray();

            $data->update($request->all());

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
