<?php

namespace App\Http\Controllers\Api\CostingHPP;

use App\Http\Controllers\Api\CostProd\CostProdController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\DetAlloc\LaporanProduksiController;
use App\Http\Controllers\Api\ProCost\ProcostController;
use App\Models\cpoKpbn;
use App\Models\Debe;
use App\Models\LevyDutyBulky;
use App\Models\MarketRoutersBulky;
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

    public function indexPeriod(Request $request)
    {
        try {
            $processResult = $this->costingHpp($request);

            return response()->json([
                'data' => $processResult['data'],
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

    // public function processIndexPeriodCostingHPP($request)
    // {
    //     $laporanProduksi = $this->processRecapData($request);

    //     $proCost = $this->processProCost($request);

    //     $cpoConsumeQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'CPO (Olah)');
    //     $rbdpoQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'RBDPO (Produksi)');
    //     $pfadQty = $this->getTotalQty($laporanProduksi['laporanProduksi'], 'Refinery', 'PFAD (Produksi)');

    //     $rbdpoRendementPercentage = $this->calculatePercentage($rbdpoQty, $cpoConsumeQty);
    //     $pfadRendementPercentage = $this->calculatePercentage($pfadQty, $cpoConsumeQty);

    //     $settingDirectIds = $this->getSettingIds([
    //         'coa_bahan_baku', 'coa_bahan_bakar', 'coa_bleaching_earth',
    //         'coa_phosporic_acid', 'coa_others','coa_analisa_lab',
    //         'coa_listrik', 'coa_air'
    //     ]);

    //     $settingInDirectIds = $this->getSettingIds([
    //         'coa_gaji_tunjangan_sosial_pimpinan', 'coa_gaji_tunjangan_sosial_pelaksana',
    //         'coa_asuransi_pabrik', 'coa_limbah_pihak3', 'coa_bengkel_pemeliharaan', 'coa_depresiasi'
    //     ]);

    //     $dataDirect = $this->processGeneralLedger($request, $settingDirectIds);
    //     $dataInDirect = $this->processGeneralLedger($request, $settingInDirectIds);

    //     $directCost = $this->generateCostOutput('Refinery', $dataDirect, $cpoConsumeQty);
    //     $inDirectCost = $this->generateCostOutput('Refinery', $dataInDirect, $cpoConsumeQty);

    //     $alokasiBiaya = $laporanProduksi['alokasiBiaya']['allocation'];

    //     $refineryData = array_filter($alokasiBiaya, function($allocation) {
    //         return $allocation['nama'] === 'Refinery';
    //     });

    //     $fraksinasiData = array_filter($alokasiBiaya, function($allocation) {
    //         return $allocation['nama'] === 'Fraksinasi';
    //     });

    //     $refineryData = array_values($refineryData);
    //     $fraksinasiData = array_values($fraksinasiData);

    //     $refineryData = $refineryData[0];
    //     $fraksinasiData = $fraksinasiData[0];

    //     $percentagesRefinery = [];
    //     foreach ($refineryData['item'] as $item) {
    //         $percentagesRefinery[$item['name']] = $item['percentage'];
    //     }

    //     $percentagesFraksinasi = [];
    //     foreach ($fraksinasiData['item'] as $item) {
    //         $percentagesFraksinasi[$item['name']] = $item['percentage'];
    //     }

    //     return [
    //         'data' => [
    //             'procost' => $proCost,
    //             'laporan' => $laporanProduksi,
    //             'cpoConsume' => $cpoConsumeQty,
    //             'rbdpo' => $rbdpoQty,
    //             'pfad' => $pfadQty,
    //             'rbdpoRendementPercentage' => $rbdpoRendementPercentage,
    //             'pfadRendementPercentage' => $pfadRendementPercentage,
    //             'dataDirect' => $directCost,
    //             'dataInDirect' => $inDirectCost,
    //         ]
    //     ];
    // }

    // private function calculatePercentage($qty, $total)
    // {
    //     return $total != 0 ? ($qty / $total) * 100 : 0;
    // }

    // private function getSettingIds(array $settingNames)
    // {
    //     return Setting::whereIn('setting_name', $settingNames)->pluck('id')->toArray();
    // }

    // private function generateCostOutput($categoryName, $data, $totalQty)
    // {
    //     // Calculate the total value for all items in the category
    //     $totalValue = array_sum(array_column($data['data']->toArray(), 'result'));

    //     // Calculate total Rp per Kg for the category
    //     $totalRpPerKg = $totalQty != 0 ? $totalValue / $totalQty : 0;

    //     return [
    //         'directCost' => [
    //             [
    //                 'nama' => $categoryName,
    //                 'totalValue' => $totalValue,
    //                 'totalRpPerKg' => $totalRpPerKg,
    //                 'item' => array_map(function($dataItem) use ($totalQty) {
    //                     return [
    //                         'name' => $dataItem['nama'],
    //                         'totalValue' => $dataItem['result'],
    //                         'rpPerKg' => $totalQty != 0 ? $dataItem['result'] / $totalQty : 0
    //                     ];
    //                 }, $data['data']->toArray())
    //             ]
    //         ]
    //     ];
    // }

    // public function getTotalQty($laporanProduksi, $namaItem, $namaUraian)
    // {
    //     $totalQty = 0;

    //     foreach ($laporanProduksi as $item) {
    //         if (isset($item['nama']) && $item['nama'] === $namaItem) {
    //             if (isset($item['uraian']) && is_array($item['uraian'])) {
    //                 foreach ($item['uraian'] as $uraian) {
    //                     if (isset($uraian['nama']) && $uraian['nama'] === $namaUraian) {
    //                         $totalQty += isset($uraian['total_qty']) ? (float) $uraian['total_qty'] : 0;
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     return $totalQty;
    // }

    // public function processGeneralLedger(Request $request, $settingIds)
    // {
    //     $tanggal = Carbon::parse($request->tanggal);
    //     $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();
    //     $coa = Setting::whereIn('id', $settingIds)->orderBy('id')->get();
    //     $gl = collect($this->getGeneralLedgerData($tanggal));

    //     $laporanProduksiController = new LaporanProduksiController();
    //     $laporanData = $laporanProduksiController->index($request);

    //     $totalQtyRefineryCPO = 0;
    //     if (isset($laporanData['laporanProduksi'])) {
    //         foreach ($laporanData['laporanProduksi'] as $laporan) {
    //             if ($laporan['nama'] === 'Refinery') {
    //                 foreach ($laporan['uraian'] as $uraian) {
    //                     if ($uraian['nama'] === 'CPO (Olah)') {
    //                         $totalQtyRefineryCPO = $uraian['total_qty'];
    //                         break 2;
    //                     }
    //                 }
    //             }
    //         }
    //     }

    //     $data = $coa->map(function($coaSetting) use ($debe, $gl, $totalQtyRefineryCPO) {
    //         $coaNumbers = explode(',', $coaSetting->setting_value);
    //         $coaData = [];
    //         $totalDebitSetting = 0;
    //         $totalCreditSetting = 0;
    //         $mReportName = '';

    //         foreach ($coaNumbers as $coaNumber) {
    //             $glData = $gl->filter(function($item) use ($coaNumber) {
    //                 return $item['account_account']['code'] == $coaNumber;
    //             });

    //             $debeModel = $debe->firstWhere('coa', $coaNumber);
    //             $mReportName = $debeModel ? $debeModel->mReport->nama : '';

    //             $totalDebit = $glData->sum('debit');
    //             $totalCredit = $glData->sum('credit');
    //             $result = $totalDebit - $totalCredit;

    //             $totalDebitSetting += $totalDebit;
    //             $totalCreditSetting += $totalCredit;

    //             $coaData[] = [
    //                 'coa_number' => $coaNumber,
    //                 'debe' => $debeModel,
    //                 'gl' => $glData->values(),
    //                 'total_debit' => $totalDebit,
    //                 'total_credit' => $totalCredit,
    //                 'result' => $result
    //             ];
    //         }

    //         return [
    //             'nama' => $mReportName,
    //             'setting' => $coaSetting->setting_name,
    //             'total_debit' => $totalDebitSetting,
    //             'total_credit' => $totalCreditSetting,
    //             'result' => $totalDebitSetting - $totalCreditSetting,
    //             'total_qty_refinery_cpo_olah' => $totalQtyRefineryCPO,
    //             'rp_per_kg_cpo_olah' => $totalQtyRefineryCPO > 0 ? ($totalDebitSetting - $totalCreditSetting) / $totalQtyRefineryCPO : 0,
    //             'coa' => $coaData
    //         ];
    //     });

    //     return ['data' => $data->values()];
    // }

    // public function processRecapData(Request $request)
    // {
    //     $mata_uang = 'USD';
    //     $tanggal = Carbon::parse($request->tanggal);
    //     $year = $tanggal->year;
    //     $month = $tanggal->month;
    //     $laporanProduksiController = new LaporanProduksiController;


    //     $data = $laporanProduksiController->dataLaporanProduksi($year, $month);

    //     $laporanProduksi = $laporanProduksiController->prosesLaporanProd($data);

    //     $hargaGasSetting = $laporanProduksiController->settingGet('harga_gas');
    //     $minPemakaianGasSetting = $laporanProduksiController->settingGet('minimum_pemakaian_gas');
    //     $uraianGasIds = $laporanProduksiController->settingGet('id_uraian_gas');
    //     $uraianWaterIds = $laporanProduksiController->settingGet('id_uraian_water');
    //     $uraianSteamIds = $laporanProduksiController->settingGet('id_uraian_steam');
    //     $uraianPowerIds = $laporanProduksiController->settingGet('id_uraian_listrik');

    //     $settingPersenCostAllocAirRefinery = $laporanProduksiController->settingGet('persen_cost_alloc_air_refinery');
    //     $PersenCostAllocAirRefinery = $settingPersenCostAllocAirRefinery->setting_value;
    //     $PersenCostAllocAirFractionation = 100 - $PersenCostAllocAirRefinery;

    //     $settingPersenCostAllocGasRefinery = $laporanProduksiController->settingGet('persen_cost_alloc_air_refinery');
    //     $PersenCostAllocGasRefinery = $settingPersenCostAllocGasRefinery->setting_value;
    //     $PersenCostAllocGasFractionation = 100 - $PersenCostAllocGasRefinery;

    //     $currencyRates = collect($this->getRateCurrencyData($tanggal, $mata_uang));

    //     $additionalData = $laporanProduksiController->processUraianData($laporanProduksi, $uraianGasIds, $uraianWaterIds, $uraianSteamIds, $uraianPowerIds);

    //     $incomingSteam = null;
    //     $distributionToRef = null;
    //     $distributionToFrac = null;
    //     $distributionToOther = null;

    //     foreach ($additionalData['steamConsumption'] as $item) {
    //         if($item['id'] == 51){
    //             $incomingSteam =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 52){
    //             $distributionToRef =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if ($item['id'] == 53) {
    //             $distributionToFrac = [
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if ($item['id'] == 54) {
    //             $distributionToOther = [
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //     }

    //     $incomingPertagas = null;
    //     $incomingINL = null;
    //     $outgoingHPBoilerRefinery = null;
    //     $outgoingMPBoiler12 = null;

    //     foreach ($additionalData['gasConsumption'] as $item) {
    //         if ($item['id'] == 47) {
    //             $incomingINL = [
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if ($item['id'] == 48) {
    //             $incomingPertagas = [
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 49){
    //             $outgoingHPBoilerRefinery =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 50){
    //             $outgoingMPBoiler12 =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //     }

    //     $outgoingSoftenerProductRef = null;
    //     $outgoingSoftenerProductFrac = null;
    //     $outgoingROProduct = null;
    //     $outgoingOthers = null;
    //     $wasteWaterEffluent = null;

    //     foreach ($additionalData['waterConsumption'] as $item) {
    //         if ($item['id'] == 56) {
    //             $outgoingSoftenerProductRef = [
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 57){
    //             $outgoingSoftenerProductFrac =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 58){
    //             $outgoingROProduct =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 59){
    //             $outgoingOthers =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 60){
    //             $wasteWaterEffluent =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //     }

    //     $pemakaianListrikPLN = null;
    //     $powerAllocRefinery = null;
    //     $powerAllocFractionation = null;
    //     $powerAllocOther = null;

    //     foreach ($additionalData['powerConsumption'] as $item) {
    //         if ($item['id'] == 61) {
    //             $pemakaianListrikPLN = [
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 62){
    //             $powerAllocRefinery =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 63){
    //             $powerAllocFractionation =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //         if($item['id'] == 64){
    //             $powerAllocOther =[
    //                 'satuan' => $item['satuan'],
    //                 'value' => $item['value']
    //             ];
    //         }
    //     }

    //     $hargaGas = [
    //         'satuan' => 'USD',
    //         'value' =>$hargaGasSetting ? $hargaGasSetting->setting_value : 0
    //     ];

    //     $biayaTagihanUSD = [
    //         'satuan' => 'USD',
    //         'value' => $incomingPertagas['value'] * $hargaGas['value']
    //     ];

    //     $averageCurrencyRate = [
    //         'satuan' => 'IDR',
    //         'value' => $currencyRates->avg('rate')
    //     ];

    //     $biayaTagihanIDR = [
    //         'satuan' => 'IDR',
    //         'value' => $biayaTagihanUSD['value'] * $averageCurrencyRate['value']
    //     ];

    //     $biayaPemakaianGas = [
    //         'Incoming *based on Pertagas' => $incomingPertagas,
    //         'Harga Gas' => $hargaGas,
    //         'Nilai Biaya Tagihan USD' => $biayaTagihanUSD,
    //         'Kurs' => $averageCurrencyRate,
    //         'Nilai Biaya Tagihan IDR' => $biayaTagihanIDR
    //     ];

    //     $minPemakaianGas = [
    //         'satuan' => 'mmbtu',
    //         'value' => $minPemakaianGasSetting ? $minPemakaianGasSetting->setting_value : 0
    //     ];

    //     $plusminPemakaianGas = [
    //         'satuan' => 'mmbtu',
    //         'value' => $minPemakaianGas['value'] - $incomingPertagas['value']
    //     ];

    //     $penaltyUSD = [
    //         'satuan' => 'USD',
    //         'value' => $plusminPemakaianGas['value'] * $hargaGas['value']
    //     ];

    //     $penaltyIDR = [
    //         'satuan' => 'USD',
    //         'value' => $penaltyUSD['value'] * $averageCurrencyRate['value']
    //     ];

    //     $perhitunganPenaltyGas = [
    //         'Incoming *based on Pertagas' => $incomingPertagas,
    //         'Minimum Pemakaian' => $minPemakaianGas,
    //         '+/(-) Pemakaian Gas' => $plusminPemakaianGas,
    //         'Harga Gas' => $hargaGas,
    //         'Nilai Biaya Penalty USD' => $penaltyUSD,
    //         'Kurs' => $averageCurrencyRate,
    //         'Nilai Biaya Penalty IDR' => $penaltyIDR
    //     ];

    //     $totalPemakaianAirM3 =
    //         ($outgoingSoftenerProductRef['value'] ?? 0) +
    //         ($outgoingSoftenerProductFrac['value'] ?? 0) +
    //         ($outgoingROProduct['value'] ?? 0) +
    //         ($outgoingOthers['value'] ?? 0) +
    //         ($wasteWaterEffluent['value'] ?? 0);

    //     $refineryAllocationAir =
    //         ($outgoingSoftenerProductRef['value'] ?? 0) +
    //         ((($outgoingROProduct['value'] ?? 0) +
    //         ($outgoingOthers['value'] ?? 0) +
    //         ($wasteWaterEffluent['value'] ?? 0)) *
    //         $PersenCostAllocAirRefinery / 100);

    //     $fractionationAllocationAir =
    //         ($outgoingSoftenerProductFrac['value'] ?? 0) +
    //         ((($outgoingROProduct['value'] ?? 0) +
    //         ($outgoingOthers['value'] ?? 0) +
    //         ($wasteWaterEffluent['value'] ?? 0)) *
    //         $PersenCostAllocAirFractionation / 100);

    //     $totalPemakaianAirAllocation = $refineryAllocationAir + $fractionationAllocationAir;
    //     $refinerym3     = $outgoingSoftenerProductRef['value'] ?? 0;
    //     $fraksinasim3   = $outgoingSoftenerProductFrac['value'] ?? 0;

    //     $allocationAir  = [
    //         'Refinery (m3)' => $refinerym3,
    //         'Fraksinasi (m3)' => $fraksinasim3,
    //         'RO' => $outgoingROProduct['value'] ?? 0,
    //         'Other' => $outgoingOthers['value'] ?? 0,
    //         'Waste' => $wasteWaterEffluent['value'] ?? 0,
    //         'Total Pemakaian Air (m3)' => $totalPemakaianAirM3,
    //         'Refinery (allocation)' => $refineryAllocationAir,
    //         'Fraksinasi (allocation)' => $fractionationAllocationAir,
    //         'Total Pemakaian Air (allocation)' => $totalPemakaianAirAllocation,
    //         'Result' => $totalPemakaianAirM3 - $totalPemakaianAirAllocation,
    //     ];


    //     $hpBoilerRefineryValue = $outgoingHPBoilerRefinery['value'] ?? 0;
    //     $mpBoiler12Value = $outgoingMPBoiler12['value'] ?? 0;
    //     $totalPemakaianGasmmbtu = $hpBoilerRefineryValue + $mpBoiler12Value;

    //     $incomingSteamValue = $incomingSteam['value'] ?? 0;
    //     $refinerySteamValue = $distributionToRef['value'] ?? 0;
    //     $fractionationSteamValue = $distributionToRef['value'] ?? 0;
    //     $otherSteamValue = $distributionToRef['value'] ?? 0;

    //     $refinerySteamRatio = ($incomingSteamValue > 0) ? ($refinerySteamValue / $incomingSteamValue) : 0;
    //     $fractionationSteamRatio = ($incomingSteamValue > 0) ? ($fractionationSteamValue / $incomingSteamValue) : 0;
    //     $otherSteamRatio = ($incomingSteamValue > 0) ? ($otherSteamValue / $incomingSteamValue) : 0;

    //     $refineryAllocationGas = (($refinerySteamRatio + ($otherSteamRatio * $PersenCostAllocGasRefinery)) * $mpBoiler12Value) + $hpBoilerRefineryValue;
    //     $fractionationAllocationGas = (($fractionationSteamRatio + ($otherSteamRatio * $PersenCostAllocGasFractionation)) * $mpBoiler12Value);

    //     $totalPemakaianGasAllocation = $refineryAllocationGas + $fractionationAllocationGas;

    //     $allocationGas = [
    //         'HP Boiler Refinery' => $hpBoilerRefineryValue,
    //         'MP Boiler 1, 2' => $mpBoiler12Value,
    //         'Total Pemakaian Gas (mmbtu)' => $totalPemakaianGasmmbtu,
    //         'Refinery (allocation)' => $refineryAllocationGas,
    //         'Fraksinasi (allocation)' => $fractionationAllocationGas,
    //         'Total Pemakaian Gas (allocation)' => $totalPemakaianGasAllocation,
    //         'Result' => $totalPemakaianGasmmbtu - $totalPemakaianGasAllocation,
    //     ];

    //     $pemakaianListrikPLNValue = $pemakaianListrikPLN['value'] ?? 0;
    //     $totalListrikKwh = $pemakaianListrikPLNValue;
    //     $powerAllocRefineryValue = $powerAllocRefinery['value'] ?? 0;
    //     $powerAllocFractionationValue = $powerAllocFractionation['value'] ?? 0;
    //     $powerAllocOtherValue = $powerAllocOther['value'] ?? 0;

    //     $totalPemakaianListrik = $powerAllocRefineryValue + $powerAllocFractionationValue + $powerAllocOtherValue;
    //     $selisihListrikAlloc = $pemakaianListrikPLNValue - $totalPemakaianListrik;

    //     $totalPowerAlloc = $powerAllocRefineryValue + $powerAllocFractionationValue;
    //     $refineryAllocationListrik = ($totalPowerAlloc > 0)
    //         ? $powerAllocRefineryValue + (($selisihListrikAlloc + $powerAllocOtherValue) * ($powerAllocRefineryValue / $totalPowerAlloc))
    //         : 0;

    //     $fractionationAllocationListrik = ($totalPowerAlloc > 0)
    //         ? $powerAllocFractionationValue + (($selisihListrikAlloc + $powerAllocOtherValue) * ($powerAllocFractionationValue / $totalPowerAlloc))
    //         : 0;

    //     $totalPemakaianListrikAllocation = $refineryAllocationListrik + $fractionationAllocationListrik;

    //     $allocationPower = [
    //         'Listrik' => $pemakaianListrikPLNValue,
    //         'Total Listrik (kwh)' => $totalListrikKwh,
    //         'Refinery (allocation)' => $refineryAllocationListrik,
    //         'Fraksinasi (allocation)' => $fractionationAllocationListrik,
    //         'Total Pemakaian Listrik (allocation)' => $totalPemakaianListrikAllocation,
    //         'Result' => $totalListrikKwh - $totalPemakaianListrikAllocation,
    //     ];

    //     $percentageRefineryGas = $totalPemakaianGasAllocation ? ($refineryAllocationGas / $totalPemakaianGasAllocation) : 0;
    //     $percentageRefineryAir = $totalPemakaianAirAllocation ? ($refineryAllocationAir / $totalPemakaianAirAllocation) : 0;
    //     $percentageRefineryListrik = $totalPemakaianListrikAllocation ? ($refineryAllocationListrik / $totalPemakaianListrikAllocation) : 0;
    //     $percentageFractionationGas = $totalPemakaianGasAllocation ? ($fractionationAllocationGas / $totalPemakaianGasAllocation) : 0;
    //     $percentageFractionationAir = $totalPemakaianAirAllocation ? ($fractionationAllocationAir / $totalPemakaianAirAllocation) : 0;
    //     $percentageFractionationListrik = $totalPemakaianListrikAllocation ? ($fractionationAllocationListrik / $totalPemakaianListrikAllocation) : 0;

    //     $alokasiBiaya = [
    //         'allocation' => [
    //             [
    //                 'nama' => 'Refinery',
    //                 'item' => [
    //                     [
    //                         'name' => 'Steam / Gas',
    //                         'qty' => $refineryAllocationGas,
    //                         'percentage' => $percentageRefineryGas * 100
    //                     ],
    //                     [
    //                         'name' => 'Air',
    //                         'qty' => $refineryAllocationAir,
    //                         'percentage' => $percentageRefineryAir * 100
    //                     ],
    //                     [
    //                         'name' => 'Listrik',
    //                         'qty' => $refineryAllocationListrik,
    //                         'percentage' => $percentageRefineryListrik * 100
    //                     ]
    //                 ]
    //             ],
    //             [
    //                 'nama' => 'Fraksinasi',
    //                 'item' => [
    //                     [
    //                         'name' => 'Steam / Gas',
    //                         'qty' => $fractionationAllocationGas,
    //                         'percentage' => $percentageFractionationGas * 100
    //                     ],
    //                     [
    //                         'name' => 'Air',
    //                         'qty' => $fractionationAllocationAir,
    //                         'percentage' => $percentageFractionationAir * 100
    //                     ],
    //                     [
    //                         'name' => 'Listrik',
    //                         'qty' => $fractionationAllocationListrik,
    //                         'percentage' => $percentageFractionationListrik * 100
    //                     ]
    //                 ]
    //             ]
    //         ]
    //     ];

    //     foreach ($laporanProduksi as &$kategori) {
    //         foreach ($kategori['uraian'] as &$uraian) {
    //             $uraian['items'] = collect($uraian['items'])->groupBy('plant.id')->map(function($items, $plantId) {
    //                 $totalFinalValue = $items->sum('value');

    //                 return [
    //                     'qty' => $totalFinalValue,
    //                     'plant' => $items->first()['plant']
    //                 ];
    //             })->values()->toArray();
    //         }
    //     }

    //     $settingNames = ['konversi_liter_to_kg', 'pouch_to_box_1_liter', 'pouch_to_box_2_liter', 'konversi_m_liter_to_kg'];
    //     $settings = Setting::whereIn('setting_name', $settingNames)->get();
    //     return [
    //         'settings' => $settings,
    //         'laporanProduksi' => $laporanProduksi,
    //         'biayaPemakaianGas' => $biayaPemakaianGas,
    //         'perhitunganPenaltyGas' => $perhitunganPenaltyGas,
    //         'Allocation' => [
    //             'Air' => $allocationAir,
    //             'Gas' => $allocationGas,
    //             'Listrik' => $allocationPower
    //         ],
    //         'alokasiBiaya' => $alokasiBiaya,
    //         'message' => $this->messageAll
    //     ];
    // }

    // public function processProCost(Request $request)
    // {
    //     $tanggal = $request->tanggal;

    //     $data = $this->fetchDataMarket($tanggal);

    //     if ($data['dataMRouters']->isEmpty() || $data['dataLDuty']->isEmpty()) {
    //         return [
    //             'error' => true,
    //             'response' => response()->json(['message' => $this->messageMissing], 401),
    //         ];
    //     }

    //     $formattedDataMRouters = $data['dataMRouters']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
    //         $bulky = $items->first()['bulky'];
    //         return [
    //             'id' => $bulky['id'],
    //             'name' => $bulky['name'],
    //             'item' => $items->map(function ($item) {
    //                 return [
    //                     'id' => $item['id'],
    //                     'id_bulky' => $item['id_bulky'],
    //                     'tanggal' => $item['tanggal'],
    //                     'nilai' => $item['nilai'],
    //                     'currency_id' => $item['currency_id'],
    //                     'created_at' => $item['created_at'],
    //                     'updated_at' => $item['updated_at'],
    //                 ];
    //             })
    //         ];
    //     })->values();

    //     $formattedDataLDuty = $data['dataLDuty']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
    //         $bulky = $items->first()['bulky'];
    //         return [
    //             'id' => $bulky['id'],
    //             'name' => $bulky['name'],
    //             'item' => $items->map(function ($item) {
    //                 return [
    //                     'id' => $item['id'],
    //                     'id_bulky' => $item['id_bulky'],
    //                     'tanggal' => $item['tanggal'],
    //                     'nilai' => $item['nilai'],
    //                     'currency_id' => $item['currency_id'],
    //                     'created_at' => $item['created_at'],
    //                     'updated_at' => $item['updated_at'],
    //                 ];
    //             })
    //         ];
    //     })->values();

    //     $formattedMarketExcludedLevyDuty = $data['marketExcludedLevyDuty']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
    //         $bulky = $items->first()['bulky'];
    //         return [
    //             'id' => $bulky['id'],
    //             'name' => $bulky['name'],
    //             'item' => $items->map(function ($item) {
    //                 return [
    //                     'tanggal' => $item['tanggal'],
    //                     'nilai' => $item['nilai'],
    //                     'id_bulky' => $item['id_bulky'],
    //                     'currency_id' => $item['currency_id'],
    //                 ];
    //             })
    //         ];
    //     })->values();

    //     $averages = $this->calculateAverages($data['averageMarketValue']);
    //     // dd($averages);
    //     $laporanProduksi = $this->processRecapData($request);
    //     // dd($laporanProduksi);

    //     $costingHppController = new CostingHppController;
    //     $proCostController = new ProcostController;
    //     $produksiRefineryData = $proCostController->generateProduksiRefinery($costingHppController, $laporanProduksi, $averages);
    //     $produksiFraksinasiIV56Data = $proCostController->generateProduksiFraksinasiIV56($costingHppController, $laporanProduksi, $averages);
    //     $produksiFraksinasiIV57Data = $proCostController->generateProduksiFraksinasiIV57($costingHppController, $laporanProduksi, $averages);
    //     $produksiFraksinasiIV58Data = $proCostController->generateProduksiFraksinasiIV58($costingHppController, $laporanProduksi, $averages);
    //     $produksiFraksinasiIV60Data = $proCostController->generateProduksiFraksinasiIV60($costingHppController, $laporanProduksi, $averages);

    //     return [
    //         'error' => false,
    //         'data' => [
    //             'dataMRouters' => $formattedDataMRouters,
    //             'averageDataMRoutersPerBulky' => $data['averageDataMRoutersPerBulky'],
    //             'dataLDuty' => $formattedDataLDuty,
    //             'averageDataLDutyPerBulky' => $data['averageDataLDutyPerBulky'],
    //             'currencyRates' => $data['currencyRates'],
    //             'averageCurrencyRate' => $data['averageCurrencyRate'],
    //             'marketExcludedLevyDuty' => $formattedMarketExcludedLevyDuty,
    //             'marketUSD_or_AverageMarketExcludedLevyDutyPerBulky' => $data['averageMarketExcludedLevyDutyPerBulky'],
    //             'dataCpoKpbn' => $data['dataCpoKpbn'],
    //             'averageCpoKpbn' => $data['averageCpoKpbn'],
    //             'marketValueIDR' => $data['marketValue'],
    //             'averageMarketValueIDR' => $data['averageMarketValue'],
    //             'produksiRefineryData' => $produksiRefineryData,
    //             'produksiFraksinasiIV56Data' => $produksiFraksinasiIV56Data,
    //             'produksiFraksinasiIV57Data' => $produksiFraksinasiIV57Data,
    //             'produksiFraksinasiIV58Data' => $produksiFraksinasiIV58Data,
    //             'produksiFraksinasiIV60Data' => $produksiFraksinasiIV60Data,
    //             'message' => $this->messageAll,
    //         ],
    //     ];
    // }

    // public function fetchDataMarket($tanggal)
    // {
    //     $currencies = collect($this->getCurrencies());
    //     $currencyRates = collect($this->getRateCurrencyData($tanggal, "USD"));

    //     $dataCpoKpbn = $this->getCpoKpbn($tanggal);
    //     $dataMRouters = $this->getMarketRouters($tanggal);
    //     $dataLDuty = $this->getLevyDuty($tanggal);
    //     $setting = $this->getSetting('pembagi_market_idr');

    //     $marketExcludedLevyDuty = $this->calculateMarketExcludedLevyDuty($dataMRouters, $dataLDuty, $currencies);
    //     $averageCurrencyRate = $currencyRates->avg('rate');
    //     $averageCpoKpbn = $dataCpoKpbn->avg('avg');

    //     $averageDataMRoutersPerBulky = $this->calculateAveragePerBulky($dataMRouters);
    //     $averageDataLDutyPerBulky = $this->calculateAveragePerBulky($dataLDuty);
    //     $averageMarketExcludedLevyDutyPerBulky = $this->calculateAveragePerBulky($marketExcludedLevyDuty);

    //     $marketValue = $this->calculateMarketValue($marketExcludedLevyDuty, $currencyRates, $setting);
    //     $averageMarketValue = $this->calculateAverageMarketValue($marketValue);

    //     return compact(
    //         'dataMRouters',
    //         'dataLDuty',
    //         'dataCpoKpbn',
    //         'setting',
    //         'marketExcludedLevyDuty',
    //         'currencies',
    //         'currencyRates',
    //         'averageCurrencyRate',
    //         'averageDataMRoutersPerBulky',
    //         'averageDataLDutyPerBulky',
    //         'averageMarketExcludedLevyDutyPerBulky',
    //         'averageCpoKpbn',
    //         'marketValue',
    //         'averageMarketValue'
    //     );
    // }

    // protected function getCpoKpbn($tanggal)
    // {
    //     return cpoKpbn::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
    //         ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
    //         ->orderBy('tanggal')
    //         ->get();
    // }

    // protected function getMarketRouters($tanggal)
    // {
    //     return MarketRoutersBulky::with('bulky')
    //         ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
    //         ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
    //         ->orderBy('tanggal')
    //         ->get();
    // }

    // protected function getLevyDuty($tanggal)
    // {
    //     return LevyDutyBulky::with('bulky')
    //         ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
    //         ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
    //         ->orderBy('tanggal')
    //         ->get();
    // }

    // protected function getSetting($name)
    // {
    //     return Setting::where('setting_name', $name)->first();
    // }

    // protected function calculateMarketExcludedLevyDuty($dataMRouters, $dataLDuty, $currencies)
    // {
    //     return $dataMRouters->map(function ($router) use ($dataLDuty, $currencies) {
    //         $levyDuty = $dataLDuty->firstWhere('tanggal', $router->tanggal);
    //         $excludedValue = $router->nilai - ($levyDuty->nilai ?? 0);
    //         if (empty($router->nilai) || $router->nilai == 0) {
    //             $excludedValue = 0;
    //         }

    //         $currencyDetails = $currencies->firstWhere('id', $router->currency_id);

    //         return [
    //             'tanggal' => $router->tanggal,
    //             'nilai' => $excludedValue,
    //             'id_bulky' => $router->id_bulky,
    //             'bulky' => $router->bulky,
    //             'currency_id' => $router->currency_id,
    //             'currency' => $currencyDetails,
    //         ];
    //     });
    // }

    // protected function calculateAveragePerBulky($data)
    // {
    //     return $data->groupBy('bulky.id')->map(function ($items) {
    //         // Handle if bulky is an object or array
    //         $bulky = $items->first()->bulky ?? $items->first()['bulky'];

    //         return [
    //             'id' => $bulky['id'] ?? $bulky->id,
    //             'name' => $bulky['name'] ?? $bulky->name,
    //             'average' => $items->avg('nilai'),
    //         ];
    //     })->values();
    // }


    // protected function calculateMarketValue($marketExcludedLevyDuty, $currencyRates, $setting)
    // {
    //     $settingValue = (int) $setting->setting_value;

    //     return $marketExcludedLevyDuty->groupBy('bulky.id')->map(function ($items) use ($currencyRates, $settingValue) {
    //         $bulky = $items->first()['bulky'] ?? (object) $items->first()->bulky;

    //         return [
    //             'id' => $bulky['id'] ?? $bulky->id,
    //             'name' => $bulky['name'] ?? $bulky->name,
    //             'item' => $items->map(function ($item) use ($currencyRates, $settingValue) {
    //                 $rate = $currencyRates->firstWhere('name', $item['tanggal'])['rate'] ?? 0;
    //                 $value = ($item['nilai'] * $rate) / $settingValue;
    //                 return [
    //                     'tanggal' => $item['tanggal'],
    //                     'value' => $value,
    //                 ];
    //             })
    //         ];
    //     })->values();
    // }


    // protected function calculateAverageMarketValue($marketValue)
    // {
    //     return collect($marketValue)->map(function ($bulky) {
    //         return [
    //             'id' => $bulky['id'],
    //             'name' => $bulky['name'],
    //             'average' => round(
    //                 collect($bulky['item'])->avg('value'),
    //                 2
    //             ),
    //         ];
    //     });
    // }

    // public function calculateAverages($marketValues)
    // {
    //     $averages = [
    //         'pfad' => null,
    //         'rbdStearin' => null,
    //         'rbdOlein' => null,
    //         'rbdpo' => null
    //     ];

    //     foreach ($marketValues as $marketValue) {
    //         if ($marketValue['name'] === 'PFAD') {
    //             $averages['pfad'] = $marketValue['average'];
    //         } elseif ($marketValue['name'] === 'RBD Stearin') {
    //             $averages['rbdStearin'] = $marketValue['average'];
    //         } elseif ($marketValue['name'] === 'RBD Olein') {
    //             $averages['rbdOlein'] = $marketValue['average'];
    //         } elseif ($marketValue['name'] === 'RBDPO') {
    //             $averages['rbdpo'] = $marketValue['average'];
    //         }
    //     }

    //     return $averages;
    // }

}

