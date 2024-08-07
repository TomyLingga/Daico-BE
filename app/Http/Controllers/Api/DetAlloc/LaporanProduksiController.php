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

    public function dataLaporanProduksi($year, $month){
        $data = LaporanProduksi::whereYear('tanggal', $year)
                ->whereMonth('tanggal', $month)
                ->with(['uraian.kategori', 'hargaSatuan', 'plant'])
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

        return $data;
    }

    public function indexDate(Request $request)
    {
        try {
            $tanggal = Carbon::parse($request->tanggal);
            $year = $tanggal->year;
            $month = $tanggal->month;

            $data = $this->dataLaporanProduksi($year, $month);

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

    public function settingGet($setting_name)
    {
        $setting = Setting::where('setting_name', $setting_name)->first();

        return $setting;
    }

    public function explodeSettingIds($ids)
    {
        $exploded = explode(', ', $ids);

        return $exploded;
    }

    private function processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds)
    {
        $uraianIdsMapping = [
            'gasConsumption' => $this->explodeSettingIds($uraianGasIds->setting_value),
            'waterConsumption' => $this->explodeSettingIds($uraianWaterIds->setting_value),
            'steamConsumption' => $this->explodeSettingIds($uraianSteamIds->setting_value),
            'powerConsumption' => $this->explodeSettingIds($uraianPowerIds->setting_value),
        ];

        $additionalData = [
            'powerConsumption' => [],
            'gasConsumption' => [],
            'waterConsumption' => [],
            'steamConsumption' => [],
        ];

        foreach ($laporanProduksi as $kategori) {
            foreach ($kategori['uraian'] as $uraian) {
                foreach ($uraianIdsMapping as $category => $idsArray) {
                    if (in_array($uraian['id'], $idsArray)) {
                        $additionalData[$category][] = [
                            'id' => $uraian['id'],
                            'nama' => $uraian['nama'],
                            'satuan' => $uraian['satuan'],
                            'value' => $uraian['total_qty']
                        ];
                        break;
                    }
                }
            }
        }
        return $additionalData;
    }

    public function recapData(Request $request)
    {
        try {
            $tanggal = Carbon::parse($request->tanggal);
            $year = $tanggal->year;
            $month = $tanggal->month;

            $data = $this->dataLaporanProduksi($year, $month);

            $laporanProduksi = $this->processLaporanProduksi($data);

            $hargaGasSetting = $this->settingGet('harga_gas');
            $minPemakaianGasSetting = $this->settingGet('minimum_pemakaian_gas');
            $uraianGasIds = $this->settingGet('id_uraian_gas');
            $uraianWaterIds = $this->settingGet('id_uraian_water');
            $uraianSteamIds = $this->settingGet('id_uraian_steam');
            $uraianPowerIds = $this->settingGet('id_uraian_listrik');

            $settingPersenCostAllocAirRefinery = $this->settingGet('persen_cost_alloc_air_refinery');
            $PersenCostAllocAirRefinery = $settingPersenCostAllocAirRefinery->setting_value;
            $PersenCostAllocAirFractionation = 100 - $PersenCostAllocAirRefinery;

            $settingPersenCostAllocGasRefinery = $this->settingGet('persen_cost_alloc_air_refinery');
            $PersenCostAllocGasRefinery = $settingPersenCostAllocGasRefinery->setting_value;
            $PersenCostAllocGasFractionation = 100 - $PersenCostAllocGasRefinery;

            $currencyRates = collect($this->geturlRateCurrencyData($tanggal, "USD"));

            $additionalData = $this->processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds);

            $incomingSteam = null;
            $distributionToRef = null;
            $distributionToFrac = null;
            $distributionToOther = null;

            foreach ($additionalData['steamConsumption'] as $item) {
                if($item['id'] == 51){
                    $incomingSteam =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 52){
                    $distributionToRef =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if ($item['id'] == 53) {
                    $distributionToFrac = [
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if ($item['id'] == 54) {
                    $distributionToOther = [
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
            }

            $incomingPertagas = null;
            $incomingINL = null;
            $outgoingHPBoilerRefinery = null;
            $outgoingMPBoiler12 = null;

            foreach ($additionalData['gasConsumption'] as $item) {
                if ($item['id'] == 47) {
                    $incomingINL = [
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if ($item['id'] == 48) {
                    $incomingPertagas = [
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 49){
                    $outgoingHPBoilerRefinery =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 50){
                    $outgoingMPBoiler12 =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
            }

            $outgoingSoftenerProductRef = null;
            $outgoingSoftenerProductFrac = null;
            $outgoingROProduct = null;
            $outgoingOthers = null;
            $wasteWaterEffluent = null;

            foreach ($additionalData['waterConsumption'] as $item) {
                if ($item['id'] == 56) {
                    $outgoingSoftenerProductRef = [
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 57){
                    $outgoingSoftenerProductFrac =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 58){
                    $outgoingROProduct =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 59){
                    $outgoingOthers =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 60){
                    $wasteWaterEffluent =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
            }

            $pemakaianListrikPLN = null;
            $powerAllocRefinery = null;
            $powerAllocFractionation = null;
            $powerAllocOther = null;

            foreach ($additionalData['powerConsumption'] as $item) {
                if ($item['id'] == 61) {
                    $pemakaianListrikPLN = [
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 62){
                    $powerAllocRefinery =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 63){
                    $powerAllocFractionation =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
                if($item['id'] == 64){
                    $powerAllocOther =[
                        'satuan' => $item['satuan'],
                        'value' => $item['value']
                    ];
                }
            }

            $hargaGas = [
                'satuan' => 'USD',
                'value' =>$hargaGasSetting ? $hargaGasSetting->setting_value : 0
            ];

            $biayaTagihanUSD = [
                'satuan' => 'USD',
                'value' => $incomingPertagas['value'] * $hargaGas['value']
            ];

            $averageCurrencyRate = [
                'satuan' => 'IDR',
                'value' => $currencyRates->avg('rate')
            ];

            $biayaTagihanIDR = [
                'satuan' => 'IDR',
                'value' => $biayaTagihanUSD['value'] * $averageCurrencyRate['value']
            ];

            $biayaPemakaianGas = [
                'Incoming *based on Pertagas' => $incomingPertagas,
                'Harga Gas' => $hargaGas,
                'Nilai Biaya Tagihan USD' => $biayaTagihanUSD,
                'Kurs' => $averageCurrencyRate,
                'Nilai Biaya Tagihan IDR' => $biayaTagihanIDR
            ];

            $minPemakaianGas = [
                'satuan' => 'mmbtu',
                'value' => $minPemakaianGasSetting ? $minPemakaianGasSetting->setting_value : 0
            ];

            $plusminPemakaianGas = [
                'satuan' => 'mmbtu',
                'value' => $minPemakaianGas['value'] - $incomingPertagas['value']
            ];

            $penaltyUSD = [
                'satuan' => 'USD',
                'value' => $plusminPemakaianGas['value'] * $hargaGas['value']
            ];

            $penaltyIDR = [
                'satuan' => 'USD',
                'value' => $penaltyUSD['value'] * $averageCurrencyRate['value']
            ];

            $perhitunganPenaltyGas = [
                'Incoming *based on Pertagas' => $incomingPertagas,
                'Minimum Pemakaian' => $minPemakaianGas,
                '+/(-) Pemakaian Gas' => $plusminPemakaianGas,
                'Harga Gas' => $hargaGas,
                'Nilai Biaya Penalty USD' => $penaltyUSD,
                'Kurs' => $averageCurrencyRate,
                'Nilai Biaya Penalty IDR' => $penaltyIDR
            ];

            $totalPemakaianAirM3 =
                ($outgoingSoftenerProductRef['value'] ?? 0) +
                ($outgoingSoftenerProductFrac['value'] ?? 0) +
                ($outgoingROProduct['value'] ?? 0) +
                ($outgoingOthers['value'] ?? 0) +
                ($wasteWaterEffluent['value'] ?? 0);

            $refineryAllocationAir =
                ($outgoingSoftenerProductRef['value'] ?? 0) +
                ((($outgoingROProduct['value'] ?? 0) +
                ($outgoingOthers['value'] ?? 0) +
                ($wasteWaterEffluent['value'] ?? 0)) *
                $PersenCostAllocAirRefinery / 100);

            $fractionationAllocationAir =
                ($outgoingSoftenerProductFrac['value'] ?? 0) +
                ((($outgoingROProduct['value'] ?? 0) +
                ($outgoingOthers['value'] ?? 0) +
                ($wasteWaterEffluent['value'] ?? 0)) *
                $PersenCostAllocAirFractionation / 100);

            $totalPemakaianAirAllocation = $refineryAllocationAir + $fractionationAllocationAir;
            $refinerym3     = $outgoingSoftenerProductRef['value'] ?? 0;
            $fraksinasim3   = $outgoingSoftenerProductFrac['value'] ?? 0;

            $allocationAir  = [
                'Refinery (m3)' => $refinerym3,
                'Fraksinasi (m3)' => $fraksinasim3,
                'RO' => $outgoingROProduct['value'] ?? 0,
                'Other' => $outgoingOthers['value'] ?? 0,
                'Waste' => $wasteWaterEffluent['value'] ?? 0,
                'Total Pemakaian Air (m3)' => $totalPemakaianAirM3,
                'Refinery (allocation)' => $refineryAllocationAir,
                'Fraksinasi (allocation)' => $fractionationAllocationAir,
                'Total Pemakaian Air (allocation)' => $totalPemakaianAirAllocation,
                'Result' => $totalPemakaianAirM3 - $totalPemakaianAirAllocation,
            ];


            $hpBoilerRefineryValue = $outgoingHPBoilerRefinery['value'] ?? 0;
            $mpBoiler12Value = $outgoingMPBoiler12['value'] ?? 0;
            $totalPemakaianGasmmbtu = $hpBoilerRefineryValue + $mpBoiler12Value;

            $incomingSteamValue = $incomingSteam['value'] ?? 0;
            $refinerySteamValue = $distributionToRef['value'] ?? 0;
            $fractionationSteamValue = $distributionToRef['value'] ?? 0;
            $otherSteamValue = $distributionToRef['value'] ?? 0;

            $refinerySteamRatio = ($incomingSteamValue > 0) ? ($refinerySteamValue / $incomingSteamValue) : 0;
            $fractionationSteamRatio = ($incomingSteamValue > 0) ? ($fractionationSteamValue / $incomingSteamValue) : 0;
            $otherSteamRatio = ($incomingSteamValue > 0) ? ($otherSteamValue / $incomingSteamValue) : 0;

            $refineryAllocationGas = (($refinerySteamRatio + ($otherSteamRatio * $PersenCostAllocGasRefinery)) * $mpBoiler12Value) + $hpBoilerRefineryValue;
            $fractionationAllocationGas = (($fractionationSteamRatio + ($otherSteamRatio * $PersenCostAllocGasFractionation)) * $mpBoiler12Value);

            $totalPemakaianGasAllocation = $refineryAllocationGas + $fractionationAllocationGas;

            $allocationGas = [
                'HP Boiler Refinery' => $hpBoilerRefineryValue,
                'MP Boiler 1, 2' => $mpBoiler12Value,
                'Total Pemakaian Gas (mmbtu)' => $totalPemakaianGasmmbtu,
                'Refinery (allocation)' => $refineryAllocationGas,
                'Fraksinasi (allocation)' => $fractionationAllocationGas,
                'Total Pemakaian Gas (allocation)' => $totalPemakaianGasAllocation,
                'Result' => $totalPemakaianGasmmbtu - $totalPemakaianGasAllocation,
            ];

            $pemakaianListrikPLNValue = $pemakaianListrikPLN['value'] ?? 0;
            $totalListrikKwh = $pemakaianListrikPLNValue;
            $powerAllocRefineryValue = $powerAllocRefinery['value'] ?? 0;
            $powerAllocFractionationValue = $powerAllocFractionation['value'] ?? 0;
            $powerAllocOtherValue = $powerAllocOther['value'] ?? 0;

            $totalPemakaianListrik = $powerAllocRefineryValue + $powerAllocFractionationValue + $powerAllocOtherValue;
            $selisihListrikAlloc = $pemakaianListrikPLNValue - $totalPemakaianListrik;

            $totalPowerAlloc = $powerAllocRefineryValue + $powerAllocFractionationValue;
            $refineryAllocationListrik = ($totalPowerAlloc > 0)
                ? $powerAllocRefineryValue + (($selisihListrikAlloc + $powerAllocOtherValue) * ($powerAllocRefineryValue / $totalPowerAlloc))
                : 0;

            $fractionationAllocationListrik = ($totalPowerAlloc > 0)
                ? $powerAllocFractionationValue + (($selisihListrikAlloc + $powerAllocOtherValue) * ($powerAllocFractionationValue / $totalPowerAlloc))
                : 0;

            $totalPemakaianListrikAllocation = $refineryAllocationListrik + $fractionationAllocationListrik;

            $allocationPower = [
                'Listrik' => $pemakaianListrikPLNValue,
                'Total Listrik (kwh)' => $totalListrikKwh,
                'Refinery (allocation)' => $refineryAllocationListrik,
                'Fraksinasi (allocation)' => $fractionationAllocationListrik,
                'Total Pemakaian Listrik (allocation)' => $totalPemakaianListrikAllocation,
                'Result' => $totalListrikKwh - $totalPemakaianListrikAllocation,
            ];

            $percentageRefineryGas = $totalPemakaianGasAllocation ? ($refineryAllocationGas / $totalPemakaianGasAllocation) : 0;
            $percentageRefineryAir = $totalPemakaianAirAllocation ? ($refineryAllocationAir / $totalPemakaianAirAllocation) : 0;
            $percentageRefineryListrik = $totalPemakaianListrikAllocation ? ($refineryAllocationListrik / $totalPemakaianListrikAllocation) : 0;
            $percentageFractionationGas = $totalPemakaianGasAllocation ? ($fractionationAllocationGas / $totalPemakaianGasAllocation) : 0;
            $percentageFractionationAir = $totalPemakaianAirAllocation ? ($fractionationAllocationAir / $totalPemakaianAirAllocation) : 0;
            $percentageFractionationListrik = $totalPemakaianListrikAllocation ? ($fractionationAllocationListrik / $totalPemakaianListrikAllocation) : 0;

            $alokasiBiaya = [
                'allocation' => [
                    [
                        'nama' => 'Refinery',
                        'item' => [
                            [
                                'name' => 'Steam / Gas',
                                'qty' => $refineryAllocationGas,
                                'percentage' => $percentageRefineryGas * 100
                            ],
                            [
                                'name' => 'Air',
                                'qty' => $refineryAllocationAir,
                                'percentage' => $percentageRefineryAir * 100
                            ],
                            [
                                'name' => 'Listrik',
                                'qty' => $refineryAllocationListrik,
                                'percentage' => $percentageRefineryListrik * 100
                            ]
                        ]
                    ],
                    [
                        'nama' => 'Fraksinasi',
                        'item' => [
                            [
                                'name' => 'Steam / Gas',
                                'qty' => $fractionationAllocationGas,
                                'percentage' => $percentageFractionationGas * 100
                            ],
                            [
                                'name' => 'Air',
                                'qty' => $fractionationAllocationAir,
                                'percentage' => $percentageFractionationAir * 100
                            ],
                            [
                                'name' => 'Listrik',
                                'qty' => $fractionationAllocationListrik,
                                'percentage' => $percentageFractionationListrik * 100
                            ]
                        ]
                    ]
                ]
            ];

            foreach ($laporanProduksi as &$kategori) {
                foreach ($kategori['uraian'] as &$uraian) {
                    $uraian['items'] = collect($uraian['items'])->groupBy('plant.id')->map(function($items, $plantId) {
                        $totalFinalValue = $items->sum('value');

                        return [
                            'qty' => $totalFinalValue,
                            'plant' => $items->first()['plant']
                        ];
                    })->values()->toArray();
                }
            }

            return response()->json([
                'laporanProduksi' => $laporanProduksi,
                'biayaPemakaianGas' => $biayaPemakaianGas,
                'perhitunganPenaltyGas' => $perhitunganPenaltyGas,
                'Allocation' => [
                    'Air' => $allocationAir,
                    'Gas' => $allocationGas,
                    'Listrik' => $allocationPower
                ],
                'alokasiBiaya' => $alokasiBiaya,
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
