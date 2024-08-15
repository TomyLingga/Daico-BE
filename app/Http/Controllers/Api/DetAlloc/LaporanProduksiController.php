<?php

namespace App\Http\Controllers\Api\DetAlloc;

use App\Http\Controllers\Api\CostingHPP\CostingHppController;
use App\Http\Controllers\Controller;
use App\Models\BiayaPenyusutan;
use App\Models\HargaSatuanProduksi;
use App\Models\LaporanProduksi;
use App\Models\Plant;
use App\Models\Setting;
use App\Models\UraianProduksi;
use App\Services\LoggerService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class LaporanProduksiController extends Controller
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
                'id_uraian' => 'required|integer',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            if ($request->has('id_plant')) {
                Plant::findOrFail($request->id_plant);
            }

            UraianProduksi::findOrFail($request->id_uraian);

            $existingLaporan = LaporanProduksi::where('id_uraian', $request->id_uraian)
                ->where('tanggal', $request->tanggal)
                ->first();

            if ($existingLaporan) {
                return response()->json([
                    'message' => 'A record with the same tanggal and id_uraian already exists.',
                    'success' => false,
                ], 409);
            }

            $latestHargaSatuan = HargaSatuanProduksi::where('id_uraian_produksi', $request->id_uraian)
            ->orderBy('created_at', 'desc')
            ->first();

            if (!$latestHargaSatuan) {
                return response()->json([
                    'message' => 'No HargaSatuanProduksi found for the given id_uraian_produksi',
                    'success' => false,
                ], 404);
            }

            $data = $request->all();
            $data['id_harga_satuan'] = $latestHargaSatuan->id;

            $laporanProduksi = LaporanProduksi::create($data);

            LoggerService::logAction($this->userData, $laporanProduksi, 'create', null, $laporanProduksi->toArray());

            DB::commit();

            return response()->json([
                'data' => $laporanProduksi,
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
                'id_uraian' => 'required|integer',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            if ($request->has('id_plant')) {
                Plant::findOrFail($request->id_plant);
            }
            UraianProduksi::findOrFail($request->id_uraian);

            $latestHargaSatuan = HargaSatuanProduksi::where('id_uraian_produksi', $request->id_uraian)
            ->orderBy('created_at', 'desc')
            ->first();

            if (!$latestHargaSatuan) {
                return response()->json([
                    'message' => 'No HargaSatuanProduksi found for the given id_uraian_produksi',
                    'success' => false,
                ], 404);
            }

            $data = LaporanProduksi::findOrFail($id);
            $oldData = $data->toArray();

            $updateData = $request->all();
            $updateData['id_harga_satuan'] = $latestHargaSatuan->id;
            $data->update($updateData);

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

    public function show($id)
    {
        try {
            $data = LaporanProduksi::with(['uraian.kategori', 'hargaSatuan' , 'plant'])->find($id);

            $data['history'] = $this->formatLogs($data->logs);
            unset($data->logs);

            $data->finalValue = $data->value * $data->hargaSatuan->value;

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

    public function indexDate(Request $request)
    {
        try {
            $laporanProduksi = $this->indexLaporanProduksi($request);

            return response()->json([
                'laporanProduksi' => $laporanProduksi['laporanProduksi'],
                'setting' => $laporanProduksi['settings'],
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

    public function recapData(Request $request)
    {
        try {
            $rekap = $this->processPenyusutan($request);

            return response()->json([
                'data' => $rekap,
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
}
