<?php

namespace App\Http\Controllers\Api\DetAlloc;

use App\Http\Controllers\Controller;
use App\Models\HargaSatuanProduksi;
use App\Models\LaporanProduksi;
use App\Models\Plant;
use App\Models\Setting;
use App\Models\UraianProduksi;
use App\Services\LoggerService;
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

    public function indexDate(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $data = LaporanProduksi::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->with(['uraian.kategori', 'hargaSatuan', 'plant'])
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            $groupedData = [];
            foreach ($data as $item) {
                $kategoriName = $item->uraian->kategori->nama;
                $kategoriId = $item->uraian->kategori->id;
                $uraianName = $item->uraian->nama;

                // Calculate finalValue
                $item->finalValue = $item->value * $item->hargaSatuan->value;

                if (!isset($groupedData[$kategoriId])) {
                    $groupedData[$kategoriId] = [
                        'name' => $kategoriName,
                        'items' => []
                    ];
                }
                if (!isset($groupedData[$kategoriId]['items'][$uraianName])) {
                    $groupedData[$kategoriId]['items'][$uraianName] = [
                        'items' => [],
                        'total_value' => 0,
                        'total_finalValue' => 0
                    ];
                }
                $groupedData[$kategoriId]['items'][$uraianName]['items'][] = $item;
                $groupedData[$kategoriId]['items'][$uraianName]['total_value'] += $item->value;
                $groupedData[$kategoriId]['items'][$uraianName]['total_finalValue'] += $item->finalValue;
            }

            ksort($groupedData);

            $finalData = [];
            foreach ($groupedData as $kategoriData) {
                $kategoriName = $kategoriData['name'];
                $finalData[$kategoriName] = [];
                foreach ($kategoriData['items'] as $uraian => $group) {
                    $finalData[$kategoriName] = array_merge($finalData[$kategoriName], $group['items']);
                    $totalQtyKey = 'total qty ' . $uraian;
                    $totalFinalValueKey = 'total finalValue ' . $uraian;
                    $finalData[$kategoriName][] = [
                        $totalQtyKey => $group['total_value'],
                        $totalFinalValueKey => $group['total_finalValue']
                    ];
                }
            }

            return response()->json([
                'data' => $finalData,
                'setting' => $settings,
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

    public function show($id)
    {
        try {
            $data = LaporanProduksi::with(['uraian.kategori', 'hargaSatuan' , 'plant'])->find($id);

            // $data['history'] = $this->formatLogs($data->logs);
            // unset($data->logs);

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
}
