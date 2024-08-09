<?php

namespace App\Http\Controllers\Api\CostingHPP;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use App\Services\LoggerService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;


class CostingHppController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    // public

    public function indexPeriod(Request $request)
    {
        try {

            $laporanProduksi = $this->processRecapData($request);
            $cpoConsumeQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'CPO (Olah)');
            $rbdpoQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'RBDPO (Produksi)');
            $rbdpoRendement = $rbdpoQty/$cpoConsumeQty;
            $rbdpoRendementPercentage = $rbdpoRendement*100;
            $pfadQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'PFAD (Produksi)');
            $pfadRendement = $pfadQty/$cpoConsumeQty;
            $pfadRendementPercentage = $pfadRendement*100;

            return response()->json([
                'cpoConsume' => $cpoConsumeQty,
                'rbdpo' => $rbdpoQty,
                'pfad' => $pfadQty,
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

    private function getTotalQty($laporanProduksi, $nama)
    {
        foreach ($laporanProduksi as $item) {
            if (isset($item['uraian']) && is_array($item['uraian'])) {
                foreach ($item['uraian'] as $uraian) {
                    if (isset($uraian['nama']) && $uraian['nama'] === $nama) {
                        return isset($uraian['total_qty']) ? (float) $uraian['total_qty'] : 0;
                    }
                }
            }
        }
        return 0;
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

        $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
        $settings = Setting::whereIn('setting_name', $settingNames)->get();
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
            'message' => $this->messageAll
        ];
    }
}

