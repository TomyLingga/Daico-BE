<?php

namespace App\Http\Controllers\Api\DetAlloc;

use App\Http\Controllers\Controller;
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

    protected function processLaporanProduksi($data)
    {
        $groupedData = $data->groupBy(function($item) {
            return $item->uraian->kategori->id;
        });

        $laporanProduksi = [];

        foreach ($groupedData as $kategoriId => $items) {
            $kategoriName = $items->first()->uraian->kategori->nama;

            $uraianGroups = $items->groupBy(function($item) {
                return $item->uraian->id;
            });

            $uraianData = [];
            foreach ($uraianGroups as $uraianId => $group) {
                $uraianName = $group->first()->uraian->nama;
                $totalQty = $group->sum('value');
                $totalFinalValue = $group->sum(function($item) {
                    return $item->value * $item->hargaSatuan->value;
                });

                $itemsData = $group->sortBy('tanggal')->map(function($item) {
                    return [
                        'id' => $item->id,
                        'id_plant' => $item->id_plant,
                        'id_uraian' => $item->id_uraian,
                        'tanggal' => $item->tanggal,
                        'value' => $item->value,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'id_harga_satuan' => $item->id_harga_satuan,
                        'harga_satuan' => $item->hargaSatuan,
                        'plant' => $item->plant,
                    ];
                })->values();

                $uraianData[] = [
                    'id' => $uraianId,
                    'id_category' => $group->first()->uraian->id_category,
                    'nama' => $uraianName,
                    'satuan' => $group->first()->uraian->satuan,
                    'total_qty' => $totalQty,
                    'total_final_value' => $totalFinalValue,
                    'items' => $itemsData
                ];
            }

            $uraianData = collect($uraianData)->sortBy('id')->values()->toArray();

            $laporanProduksi[] = [
                'id' => $kategoriId,
                'nama' => $kategoriName,
                'uraian' => $uraianData
            ];
        }

        return $laporanProduksi;
    }

    public function indexDate(Request $request)
    {
        try {
            $tanggal = Carbon::parse($request->tanggal);
            $year = $tanggal->year;
            $month = $tanggal->month;

            $data = LaporanProduksi::whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->with(['uraian.kategori', 'hargaSatuan', 'plant'])
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            $laporanProduksi = $this->processLaporanProduksi($data);

            return response()->json([
                'laporanProduksi' => $laporanProduksi,
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
    public function recapData(Request $request)
    {
        try {
            $tanggal = Carbon::parse($request->tanggal);
            $year = $tanggal->year;
            $month = $tanggal->month;

            $data = LaporanProduksi::whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->with(['uraian.kategori', 'hargaSatuan', 'plant'])
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $laporanProduksi = $this->processLaporanProduksi($data);

            foreach ($laporanProduksi as &$kategori) {
                foreach ($kategori['uraian'] as &$uraian) {
                    $uraian['items'] = collect($uraian['items'])->groupBy('plant.id')->map(function($items, $plantId) {
                        $totalQty = $items->sum('qty');
                        $totalFinalValue = $items->sum('value');

                        return [
                            'qty' => $totalQty,
                            'value' => $totalFinalValue,
                            'plant' => $items->first()['plant']
                        ];
                    })->values()->toArray();
                }
            }

            return response()->json([
                'laporanProduksi' => $laporanProduksi,
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
}
