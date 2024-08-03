<?php

namespace App\Http\Controllers\Api\Cash;

use App\Http\Controllers\Controller;
use App\Models\MasterRekening;
use App\Models\RekeningUnitKerja;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RekeningUnitKerjaController extends Controller
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
            $data = RekeningUnitKerja::with(['rekening.jenis', 'rekening.tipe'])->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            foreach ($data as $entry) {
                $rekening = $entry->rekening;
                if ($rekening) {
                    $matauang = $this->getCurrency($rekening->matauang_id);
                    $rekening->matauang = $matauang;
                }
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
            $data = RekeningUnitKerja::with(['rekening.jenis', 'rekening.tipe'])->findOrFail($id);
            $rekening = $data->rekening;
                if ($rekening) {
                    $matauang = $this->getCurrency($rekening->matauang_id);
                    $rekening->matauang = $matauang;
                }

            $data->history = $this->formatLogs($data->logs);
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
                'success' => false,
            ], 500);
        }
    }

    public function indexLatest()
    {
        try {
            $latestEntries = RekeningUnitKerja::select('rekening_id', DB::raw('MAX(tanggal) as latest_date'))
                ->groupBy('rekening_id');

            $data = RekeningUnitKerja::joinSub($latestEntries, 'latest_entries', function ($join) {
                    $join->on('master_rekening_unit_kerja.rekening_id', '=', 'latest_entries.rekening_id')
                         ->on('master_rekening_unit_kerja.tanggal', '=', 'latest_entries.latest_date');
                })
                ->with(['rekening.jenis', 'rekening.tipe'])
                ->orderBy('master_rekening_unit_kerja.rekening_id')
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            foreach ($data as $entry) {
                $rekening = $entry->rekening;
                if ($rekening) {
                    $matauang = $this->getCurrency($rekening->matauang_id);
                    $rekening->matauang = $matauang;
                }
            }

            $totalCash = $data->sum('value');

            return response()->json([
                'data' => $data,
                'TotalCash' => $totalCash,
                'message' => $this->messageAll
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

    public function indexTipe($id)
    {
        try {
            $latestEntries = RekeningUnitKerja::select('rekening_id', DB::raw('MAX(tanggal) as latest_date'))
                ->whereHas('rekening', function ($query) use ($id) {
                    $query->where('tipe_id', $id);
                })
                ->groupBy('rekening_id');

            $data = RekeningUnitKerja::joinSub($latestEntries, 'latest_entries', function ($join) {
                    $join->on('master_rekening_unit_kerja.rekening_id', '=', 'latest_entries.rekening_id')
                        ->on('master_rekening_unit_kerja.tanggal', '=', 'latest_entries.latest_date');
                })
                ->with(['rekening.jenis', 'rekening.tipe'])
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            foreach ($data as $entry) {
                $rekening = $entry->rekening;
                if ($rekening) {
                    $matauang = $this->getCurrency($rekening->matauang_id);
                    $rekening->matauang = $matauang;
                }
            }

            $totalCash = $data->sum('value');

            return response()->json([
                'data' => $data,
                'TotalCash' => $totalCash,
                'message' => $this->messageAll
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

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $rules = [
                'rekening_id' => 'required|exists:' . MasterRekening::class . ',id',
                'tanggal' => 'required|date',
                'value' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $data = RekeningUnitKerja::create($request->all());

            LoggerService::logAction($this->userData, $data, 'create', null, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $rules = [
                'rekening_id' => 'required|exists:' . MasterRekening::class . ',id',
                'tanggal' => 'required|date',
                'value' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $data = RekeningUnitKerja::findOrFail($id);
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
