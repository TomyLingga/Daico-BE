<?php

namespace App\Http\Controllers\Api\Debe;

use App\Http\Controllers\Controller;
use App\Models\Allocation;
use App\Models\Category3;
use App\Models\cCentre;
use App\Models\Debe;
use App\Models\mReport;
use App\Models\Plant;
use App\Services\LoggerService;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class DebeController extends Controller
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
            $debe = Debe::with(['cat3.cat2.cat1', 'mReport', 'cCentre', 'plant', 'allocation'])->get();

            return $debe->isEmpty()
                ? response()->json(['message' => $this->messageMissing], 401)
                : response()->json(['data' => $debe, 'message' => $this->messageAll], 200);
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
            $debe = Debe::with(['cat3.cat2.cat1', 'mReport', 'cCentre', 'plant', 'allocation'])->findOrFail($id);

            // $debe->history = $this->formatLogs($debe->logs);
            // unset($debe->logs);

            return response()->json([
                'debe' => $debe,
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
    // Category3::findOrFail($request->id_category3);
    //         mReport::findOrFail($request->id_m_report);
    //         cCentre::findOrFail($request->id_c_centre);
    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $rules = [
                'coa' => 'required|unique:debe,coa',
                'id_category3' => 'required|exists:' . Category3::class . ',id',
                'id_m_report' => 'required|exists:' . MReport::class . ',id',
                'id_c_centre' => 'required|exists:' . CCentre::class . ',id',
            ];

            if ($request->has('id_plant')) {
                $rules['id_plant'] = 'exists:' . Plant::class . ',id';
            }

            if ($request->has('id_allocation')) {
                $rules['id_allocation'] = 'exists:' . Allocation::class . ',id';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $data = Debe::create($request->all());

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
            $rules = [
                'coa' => 'required|unique:debe,coa,' . $id,
                'id_category3' => 'required|exists:' . Category3::class . ',id',
                'id_m_report' => 'required|exists:' . MReport::class . ',id',
                'id_c_centre' => 'required|exists:' . CCentre::class . ',id',
            ];

            if ($request->has('id_plant')) {
                $rules['id_plant'] = 'exists:' . Plant::class . ',id';
            }

            if ($request->has('id_allocation')) {
                $rules['id_allocation'] = 'exists:' . Allocation::class . ',id';
            }

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $data = Debe::findOrFail($id);
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
