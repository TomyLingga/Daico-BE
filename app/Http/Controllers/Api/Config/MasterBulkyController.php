<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\MasterBulky;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterBulkyController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function index()
    {
        try {
            $mBulky = MasterBulky::all();

            return $mBulky->isEmpty()
                ? response()->json(['message' => $this->messageMissing], 401)
                : response()->json(['mBulky' => $mBulky, 'message' => $this->messageAll], 200);
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
            $mBulky = MasterBulky::findOrFail($id);

            $mBulky->history = $this->formatLogs($mBulky->logs);
            unset($mBulky->logs);

            return response()->json([
                'mBulky' => $mBulky,
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

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:master_bulky,name',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $data = MasterBulky::create($request->all());

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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $validator = Validator::make($request->all(), [
                'name' => 'required|unique:master_bulky,name,' . $id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false
                ], 400);
            }

            $mBulky = MasterBulky::find($id);

            if (!$mBulky) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    // 'code' => 401
                ], 401);
            }

            $dataToUpdate = [
                'name' => $request->filled('name') ? $request->name : $mBulky->name,
            ];

            $oldData = $mBulky->toArray();
            $mBulky->update($dataToUpdate);

            LoggerService::logAction($this->userData, $mBulky, 'update', $oldData, $mBulky->toArray());

            DB::commit();

            return response()->json([
                'data' => $mBulky,
                'message' => $this->messageUpdate,
                // 'code' => 200,
                'success' => true
            ], 200);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                // 'code' => 500,
                'success' => false
            ], 500);
        }
    }
}
