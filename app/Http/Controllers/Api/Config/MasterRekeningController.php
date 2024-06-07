<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\MasterJenisRekening;
use App\Models\MasterMataUang;
use App\Models\MasterRekening;
use App\Models\MasterTipeRekening;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MasterRekeningController extends Controller
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
            $data = MasterRekening::with(['jenis', 'tipe'])->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            foreach ($data as $entry) {
                $currency = $this->getCurrency($entry->matauang_id);
                $entry->matauang = $currency;
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

    public function show($id)
    {
        try {
            $data = MasterRekening::with(['jenis', 'tipe'])->findOrFail($id);
            $currency = $this->getCurrency($data->matauang_id);
            $data->matauang = $currency;
            // $data->history = $this->formatLogs($data->logs);
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

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $rules = [
                'nama' => 'required',
                'nomor' => 'required|unique:master_rekening,nomor',
                'matauang_id' => 'required|integer',
                'jenis_id' => 'required|exists:' . MasterJenisRekening::class . ',id',
                'tipe_id' => 'nullable|exists:' . MasterTipeRekening::class . ',id',
                'keterangan' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $currency = $this->getCurrency($request->matauang_id);

            if (empty($currency)) {
                return response()->json([
                    'message' => 'Currency not found',
                    'success' => false,
                ], 404);
            }

            $data = MasterRekening::create([
                'nama' => $request->nama,
                'nomor' => $request->nomor,
                'matauang_id' => $request->matauang_id, // Use the matauang_id from the request
                'jenis_id' => $request->jenis_id,
                'tipe_id' => $request->tipe_id, // This is optional and will be null if not provided
            ]);

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
                'nama' => 'required',
                'nomor' => 'required|unique:master_rekening,nomor,' . $id,
                'matauang_id' => 'required|integer',
                'jenis_id' => 'required|exists:' . MasterJenisRekening::class . ',id',
                'tipe_id' => 'required|exists:' . MasterTipeRekening::class . ',id',
                'keterangan' => 'required',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $data = MasterRekening::findOrFail($id);
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
