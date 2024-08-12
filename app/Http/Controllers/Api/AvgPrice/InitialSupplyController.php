<?php

namespace App\Http\Controllers\Api\AvgPrice;

use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Http\Controllers\Controller;
use App\Models\BiayaPenyusutan;
use App\Models\InitialSupply;
use App\Models\MasterBulky;
use App\Models\MasterProduct;
use App\Models\MasterSubProduct;
use App\Models\Setting;
use App\Services\LoggerService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class InitialSupplyController extends Controller
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
                'productable_id' => 'required|integer',
                'product_type' => 'required|string|in:bulk,product,subproduct',
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $productableType = null;
            if ($request->product_type === 'bulk') {
                MasterBulky::findOrFail($request->productable_id);
                $productableType = MasterBulky::class;
            } else if ($request->product_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->product_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $tanggal = Carbon::parse($request->tanggal);
            $month = $tanggal->format('m');
            $year = $tanggal->format('Y');

            $existingRecord = InitialSupply::where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->whereMonth('tanggal', $month)
                ->whereYear('tanggal', $year)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'A record already exists for this productable in the given month.',
                    'success' => false,
                ], 400);
            }

            $real = new InitialSupply();
            $real->productable_id = $request->productable_id;
            $real->productable_type = $productableType;
            $real->tanggal = $request->tanggal;
            $real->qty = $request->qty;
            $real->harga = $request->harga;

            $real->save();

            LoggerService::logAction($this->userData, $real, 'create', null, $real->toArray());

            DB::commit();

            return response()->json([
                'data' => $real,
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

    public function index()
    {
        try {
            $data = InitialSupply::with('productable')->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
            });

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
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
            $data = InitialSupply::with('productable')
                                ->findOrFail($id);
            $data->makeHidden('productable');
            $data->extended_productable;

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
                'productable_id' => 'required|integer',
                'product_type' => 'required|string|in:bulk,product,subproduct',
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $productableType = null;
            if ($request->product_type === 'bulk') {
                MasterBulky::findOrFail($request->productable_id);
                $productableType = MasterBulky::class;
            } else if ($request->product_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->product_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $data = InitialSupply::findOrFail($id);
            $oldData = $data->toArray();

            $tanggal = Carbon::parse($request->tanggal);
            $month = $tanggal->format('m');
            $year = $tanggal->format('Y');

            $existingRecord = InitialSupply::where('productable_id', $request->productable_id)
                ->where('productable_type', $productableType)
                ->whereMonth('tanggal', $month)
                ->whereYear('tanggal', $year)
                ->where('id', '!=', $id)
                ->first();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'A record already exists for this productable in the given month.',
                    'success' => false,
                ], 400);
            }

            $data->productable_id = $request->productable_id;
            $data->productable_type = $productableType;
            $data->tanggal = $request->tanggal;
            $data->qty = $request->qty;
            $data->harga = $request->harga;
            $data->save();

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
            $data = $this->processAvgPrice($request);

            return response()->json([
                'data' => $data,
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

    public function processAvgPrice(Request $request)
    {
        $tanggal = $request->tanggal;

        $persediaanAwal = InitialSupply::with('productable')
            ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->get();

        $persediaanAwal->each(function ($item) {
            $item->makeHidden('productable');
            $item->extended_productable;
        });

        if ($persediaanAwal->isEmpty()) {
            return response()->json(['message' => $this->messageMissing], 401);
        }

        $detAlloc = $this->processRecapData($request);

        $transformedPersediaanAwal = $persediaanAwal->map(function ($item) {
            return [
                'extended_productable' => [
                    'id' => $item->extended_productable['id'],
                    'product_id' => $item->extended_productable['product_id'] ?? null,
                    'nama' => $item->extended_productable['nama'] ?? $item->extended_productable['name'],
                    'product' => $item->extended_productable['product'] ?? null,
                    'tanggal' => $item->tanggal,
                    'qty' => $item->qty,
                    'harga' => $item->harga,
                    'jumlah' => $item->qty * $item->harga,
                ]
            ];
        });

        return [
            'persediaanAwal' => $transformedPersediaanAwal,
            'detAlloc' => $detAlloc
        ];
    }

    public function processRecapData(Request $request)
    {
        $mata_uang = 'USD';
        $tanggal = Carbon::parse($request->tanggal);
        $year = $tanggal->year;
        $month = $tanggal->month;
        $laporanProduksiController = new LaporanProduksiController;
        $data = $laporanProduksiController->dataLaporanProduksi($year, $month);

        $laporanProduksi = $laporanProduksiController->prosesLaporanProd($data);

        $hargaGasSetting = $laporanProduksiController->settingGet('harga_gas');
        $minPemakaianGasSetting = $laporanProduksiController->settingGet('minimum_pemakaian_gas');
        $uraianGasIds = $laporanProduksiController->settingGet('id_uraian_gas');
        $uraianWaterIds = $laporanProduksiController->settingGet('id_uraian_water');
        $uraianSteamIds = $laporanProduksiController->settingGet('id_uraian_steam');
        $uraianPowerIds = $laporanProduksiController->settingGet('id_uraian_listrik');

        $settingPersenCostAllocAirRefinery = $laporanProduksiController->settingGet('persen_cost_alloc_air_refinery');
        $PersenCostAllocAirRefinery = $settingPersenCostAllocAirRefinery->setting_value;
        $PersenCostAllocAirFractionation = 100 - $PersenCostAllocAirRefinery;

        $settingPersenCostAllocGasRefinery = $laporanProduksiController->settingGet('persen_cost_alloc_air_refinery');
        $PersenCostAllocGasRefinery = $settingPersenCostAllocGasRefinery->setting_value;
        $PersenCostAllocGasFractionation = 100 - $PersenCostAllocGasRefinery;

        $currencyRates = collect($this->getRateCurrencyData($tanggal, $mata_uang));
        // dd($currencyRates);


        $additionalData = $laporanProduksiController->processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds);


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
            'incomingBasedOnPertagas' => $incomingPertagas,
            'hargaGas' => $hargaGas,
            'nilaiBiayaTagihanUSD' => $biayaTagihanUSD,
            'Kurs' => $averageCurrencyRate,
            'nilaiBiayaTagihanIDR' => $biayaTagihanIDR
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
            'incomingBasedOnPertagas' => $incomingPertagas,
            'minimumPemakaian' => $minPemakaianGas,
            'plusMinusPemakaianGas' => $plusminPemakaianGas,
            'hargaGas' => $hargaGas,
            'nilaiBiayaPenaltyUSD' => $penaltyUSD,
            'Kurs' => $averageCurrencyRate,
            'nilaiBiayaPenaltyIDR' => $penaltyIDR
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
            'refinery_m3' => $refinerym3,
            'fraksinasi_m3' => $fraksinasim3,
            'ro' => $outgoingROProduct['value'] ?? 0,
            'other' => $outgoingOthers['value'] ?? 0,
            'waste' => $wasteWaterEffluent['value'] ?? 0,
            'totalPemakaianAir_m3' => $totalPemakaianAirM3,
            'refinery_allocation' => $refineryAllocationAir,
            'fraksinasi_allocation' => $fractionationAllocationAir,
            'totalPemakaianAir_allocation' => $totalPemakaianAirAllocation,
            'result' => $totalPemakaianAirM3 - $totalPemakaianAirAllocation,
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
            'hpBoilerRefinery' => $hpBoilerRefineryValue,
            'mpBoiler12' => $mpBoiler12Value,
            'totalPemakaianGas_mmbtu' => $totalPemakaianGasmmbtu,
            'refinery_allocation' => $refineryAllocationGas,
            'fraksinasi_allocation' => $fractionationAllocationGas,
            'totalPemakaianGas_allocation' => $totalPemakaianGasAllocation,
            'result' => $totalPemakaianGasmmbtu - $totalPemakaianGasAllocation,
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
            'listrik' => $pemakaianListrikPLNValue,
            'totalListrik_kwh' => $totalListrikKwh,
            'refinery_allocation' => $refineryAllocationListrik,
            'fraksinasi_allocation' => $fractionationAllocationListrik,
            'totalPemakaianListrik_allocation' => $totalPemakaianListrikAllocation,
            'result' => $totalListrikKwh - $totalPemakaianListrikAllocation,
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

        $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
        $settings = Setting::whereIn('setting_name', $settingNames)->get();

        $subQuery = BiayaPenyusutan::select('alokasi_id', DB::raw('MAX(tanggal) as latest_date'))
                ->groupBy('alokasi_id');

                $penyusutan = BiayaPenyusutan::with('allocation')
                ->joinSub($subQuery, 'latest', function($join) {
                    $join->on('biaya_penyusutan.alokasi_id', '=', 'latest.alokasi_id')
                        ->on('biaya_penyusutan.tanggal', '=', 'latest.latest_date');
                })
                ->get();

            $unitQty = [];
            $unitPercent = [];
            $totalUnitQty = 0;

            foreach ($penyusutan as $item) {
                $totalUnitQty += $item->value;
            }

            foreach ($penyusutan as $item) {
                $unitQty[] = [
                    'name' => $item->allocation->nama,
                    'value' => $item->value,
                ];

                $unitPercent[] = [
                    'name' => $item->allocation->nama,
                    'value' => $item->value / $totalUnitQty * 100,
                ];
            }

            $biayaPenyusutanUnit = [
                'name' => 'Unit',
                'columns' => [
                    [
                        'name' => 'Qty',
                        'total' => $totalUnitQty,
                        'alokasi' => $unitQty,
                    ],
                    [
                        'name' => '%',
                        'total' => 100,
                        'alokasi' => $unitPercent,
                    ]
                ]
            ];
        // $costingHppController = new CostingHppController;
        // $cpoConsumeQty = $costingHppController->getTotalQty($laporanProduksi, 'Refinery', 'CPO (Olah)');

        // $rbdpoQty = $costingHppController->getTotalQty($laporanProduksi, 'Refinery', 'RBDPO (Produksi)');
        // // $rbdpoRendement = $cpoConsumeQty != 0 ? $rbdpoQty / $cpoConsumeQty : 0;
        // // $rbdpoRendementPercentage = $rbdpoRendement * 100;

        // $pfadQty = $costingHppController->getTotalQty($laporanProduksi, 'Refinery', 'PFAD (Produksi)');
        // // $pfadRendement = $cpoConsumeQty != 0 ? $pfadQty / $cpoConsumeQty : 0;
        // // $pfadRendementPercentage = $pfadRendement * 100;
        // $rbdpoOlahIV56Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-56)', 'RBDPO (Olah)');
        // $rbdpoOlahIV57Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-57)', 'RBDPO (Olah)');
        // $rbdpoOlahIV58Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-58)', 'RBDPO (Olah)');
        // $rbdpoOlahIV60Qty = $costingHppController->getTotalQty($laporanProduksi['laporanProduksi'], 'Fraksinasi (IV-60)', 'RBDPO (Olah)');

        // $allProductionRefinery = $rbdpoQty + $pfadQty;
        // $allProductionFraksinasi = $rbdpoOlahIV56Qty + $rbdpoOlahIV57Qty+$rbdpoOlahIV58Qty+$rbdpoOlahIV60Qty;

        // $production = [
        //     'allProduction' => [
        //         [
        //             'nama' => 'Refinery',
        //             'value' => $allProductionRefinery,
        //         ],
        //         [
        //             'nama' => 'Fraksinasi',
        //             'item' => [
        //                 [
        //                     'name' => 'Steam / Gas',
        //                     'qty' => $fractionationAllocationGas,
        //                     'percentage' => $percentageFractionationGas * 100
        //                 ],
        //                 [
        //                     'name' => 'Air',
        //                     'qty' => $fractionationAllocationAir,
        //                     'percentage' => $percentageFractionationAir * 100
        //                 ],
        //                 [
        //                     'name' => 'Listrik',
        //                     'qty' => $fractionationAllocationListrik,
        //                     'percentage' => $percentageFractionationListrik * 100
        //                 ]
        //             ]
        //         ]
        //     ]
        // ];


        return [
            'settings' => $settings,
            'laporanProduksi' => $laporanProduksi,
            'biayaPemakaianGas' => $biayaPemakaianGas,
            'perhitunganPenaltyGas' => $perhitunganPenaltyGas,
            'Allocation' => [
                'Air' => $allocationAir,
                'Gas' => $allocationGas,
                'Listrik' => $allocationPower
            ],
            'alokasiBiaya' => $alokasiBiaya,
            'biayaPenyusutan' => $biayaPenyusutanUnit,
            'message' => $this->messageAll
        ];
    }
}
