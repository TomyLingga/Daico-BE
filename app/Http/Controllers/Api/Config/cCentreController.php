<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\cCentre;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use App\Services\LoggerService;

class cCentreController extends Controller
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
            $cCentres = cCentre::all();

            return $cCentres->isEmpty()
                ? response()->json(['message' => $this->messageMissing], 401)
                : response()->json(['cCenter' => $cCentres, 'message' => $this->messageAll], 200);
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
            $cCenter = cCentre::findOrFail($id);

            // $cCenter->history = $this->formatLogs($cCenter->logs);
            // unset($cCenter->logs);

            return response()->json([
                'cCenter' => $cCenter,
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
                'nama' => 'required|unique:c_centre,nama',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $data = cCentre::create($request->all());

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
                'nama' => 'required|unique:c_centre,nama,' . $id,
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false
                ], 400);
            }

            $cCenter = cCentre::find($id);

            if (!$cCenter) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    // 'code' => 401
                ], 401);
            }

            $dataToUpdate = [
                'nama' => $request->filled('nama') ? $request->nama : $cCenter->nama,
            ];

            $oldData = $cCenter->toArray();
            $cCenter->update($dataToUpdate);

            LoggerService::logAction($this->userData, $cCenter, 'update', $oldData, $cCenter->toArray());

            DB::commit();

            return response()->json([
                'data' => $cCenter,
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
