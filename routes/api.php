<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//allocation
Route::get('allocation', [App\Http\Controllers\Api\Config\AllocationController::class, 'index']);
Route::get('allocation/get/{id}', [App\Http\Controllers\Api\Config\AllocationController::class, 'show']);
//cCenter
Route::get('cCenter', [App\Http\Controllers\Api\Config\cCentreController::class, 'index']);
Route::get('cCenter/get/{id}', [App\Http\Controllers\Api\Config\cCentreController::class, 'show']);
//mReport
Route::get('mReport', [App\Http\Controllers\Api\Config\mReportController::class, 'index']);
Route::get('mReport/get/{id}', [App\Http\Controllers\Api\Config\mReportController::class, 'show']);
//plant
Route::get('plant', [App\Http\Controllers\Api\Config\PlantController::class, 'index']);
Route::get('plant/get/{id}', [App\Http\Controllers\Api\Config\PlantController::class, 'show']);
//category1
Route::get('category1', [App\Http\Controllers\Api\Config\Category1Controller::class, 'index']);
Route::get('category1/get/{id}', [App\Http\Controllers\Api\Config\Category1Controller::class, 'show']);
//category2
Route::get('category2/c1/{cat1}', [App\Http\Controllers\Api\Config\Category2Controller::class, 'indexCat1']);
Route::get('category2/get/{id}', [App\Http\Controllers\Api\Config\Category2Controller::class, 'show']);
//category3
Route::get('category3/c2/{cat2}', [App\Http\Controllers\Api\Config\Category3Controller::class, 'indexCat2']);
Route::get('category3/get/{id}', [App\Http\Controllers\Api\Config\Category3Controller::class, 'show']);
//DB
Route::get('debe', [App\Http\Controllers\Api\Debe\DebeController::class, 'index']);
Route::get('debe/get/{id}', [App\Http\Controllers\Api\Debe\DebeController::class, 'show']);
//Actual CPO
Route::get('actual-cpo', [App\Http\Controllers\Api\CPO\ActualController::class, 'index']);
Route::get('actual-cpo/get/{id}', [App\Http\Controllers\Api\CPO\ActualController::class, 'show']);
Route::post('actual-cpo/date', [App\Http\Controllers\Api\CPO\ActualController::class, 'indexDate']);
//Outstanding CPO
Route::get('outstanding-cpo', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'index']);
Route::get('outstanding-cpo/get/{id}', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'show']);
//CPO KPBN
Route::get('cpo-kpbn', [App\Http\Controllers\Api\CPO\KpbnController::class, 'index']);
Route::get('cpo-kpbn/get/{id}', [App\Http\Controllers\Api\CPO\KpbnController::class, 'show']);
Route::post('cpo-kpbn/date', [App\Http\Controllers\Api\CPO\KpbnController::class, 'indexDate']);
//Master Bulky
Route::get('bulky', [App\Http\Controllers\Api\Config\MasterBulkyController::class, 'index']);
Route::get('bulky/get/{id}', [App\Http\Controllers\Api\Config\MasterBulkyController::class, 'show']);
//Master Bulky Produksi
Route::get('bulky-prod', [App\Http\Controllers\Api\Config\MasterBulkProduksiController::class, 'index']);
Route::get('bulky-prod/get/{id}', [App\Http\Controllers\Api\Config\MasterBulkProduksiController::class, 'show']);
//Master Retail Produksi
Route::get('retail-prod', [App\Http\Controllers\Api\Config\MasterRetailProduksiController::class, 'index']);
Route::get('retail-prod/get/{id}', [App\Http\Controllers\Api\Config\MasterRetailProduksiController::class, 'show']);
//LevyDuty
Route::get('levy-duty', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'index']);
Route::get('levy-duty/get/{id}', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'show']);
Route::post('levy-duty/date', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'indexDate']);
//MarketRouter
Route::get('market-router', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'index']);
Route::get('market-router/get/{id}', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'show']);
Route::post('market-router/date', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'indexDate']);
//Setting
Route::get('settings', [App\Http\Controllers\Api\Config\SettingController::class, 'index']);
Route::get('settings/id/{id}', [App\Http\Controllers\Api\Config\SettingController::class, 'show']);
Route::get('settings/name/{name}', [App\Http\Controllers\Api\Config\SettingController::class, 'showName']);
//General Ledger
Route::post('general-ledger/date', [App\Http\Controllers\Api\GL\GeneralLedgerController::class, 'index_period']);
//Cost Prod
Route::post('cost-prod/get', [App\Http\Controllers\Api\CostProd\CostProdController::class, 'indexPeriodCoaName']);
Route::post('beban-prod/get', [App\Http\Controllers\Api\CostProd\CostProdController::class, 'indexPeriod']);
//Kategori Uraian Produksi
Route::get('kategori-produksi', [App\Http\Controllers\Api\Config\KategoriUraianProduksiController::class, 'index']);
Route::get('kategori-produksi/get/{id}', [App\Http\Controllers\Api\Config\KategoriUraianProduksiController::class, 'show']);

//Uraian
Route::get('uraian-produksi', [App\Http\Controllers\Api\Config\UraianProduksiController::class, 'index']);
Route::get('uraian-produksi/get/{id}', [App\Http\Controllers\Api\Config\UraianProduksiController::class, 'show']);
Route::get('uraian-produksi/kategori/{id}', [App\Http\Controllers\Api\Config\UraianProduksiController::class, 'indexGrup']);
//Laporan Produksi
Route::post('laporan-produksi/date', [App\Http\Controllers\Api\DetAlloc\LaporanProduksiController::class, 'indexDate']);
Route::post('laporan-produksi/recap', [App\Http\Controllers\Api\DetAlloc\LaporanProduksiController::class, 'recapData']);
Route::get('laporan-produksi/get/{id}', [App\Http\Controllers\Api\DetAlloc\LaporanProduksiController::class, 'show']);
//Daily DMO
Route::get('daily-dmo', [App\Http\Controllers\Api\Target\DailyDmoController::class, 'index']);
Route::get('daily-dmo/get/{id}', [App\Http\Controllers\Api\Target\DailyDmoController::class, 'show']);
Route::post('daily-dmo/date', [App\Http\Controllers\Api\Target\DailyDmoController::class, 'indexDate']);
//Daily DMO
Route::get('monthly-dmo', [App\Http\Controllers\Api\Target\MonthlyDmoController::class, 'index']);
Route::get('monthly-dmo/get/{id}', [App\Http\Controllers\Api\Target\MonthlyDmoController::class, 'show']);
Route::post('monthly-dmo/date', [App\Http\Controllers\Api\Target\MonthlyDmoController::class, 'indexDate']);
//Target Real
Route::get('target-real', [App\Http\Controllers\Api\Target\TargetRealController::class, 'index']);
Route::get('target-real/get/{id}', [App\Http\Controllers\Api\Target\TargetRealController::class, 'show']);
Route::post('target-real/date', [App\Http\Controllers\Api\Target\TargetRealController::class, 'indexDate']);
//Target RKAP
Route::get('target-rkap', [App\Http\Controllers\Api\Target\TargetRkapController::class, 'index']);
Route::get('target-rkap/get/{id}', [App\Http\Controllers\Api\Target\TargetRkapController::class, 'show']);
Route::post('target-rkap/date', [App\Http\Controllers\Api\Target\TargetRkapController::class, 'indexDate']);
//Target Recap
Route::post('target-recap/date', [App\Http\Controllers\Api\Target\RecapTargetController::class, 'recapTarget']);

//Harga Satuan Produksi
Route::get('harga-satuan', [App\Http\Controllers\Api\Config\HargaSatuanProduksiController::class, 'index']);
Route::get('harga-satuan/get/{id}', [App\Http\Controllers\Api\Config\HargaSatuanProduksiController::class, 'show']);
Route::get('harga-satuan/latest', [App\Http\Controllers\Api\Config\HargaSatuanProduksiController::class, 'indexLatest']);

//KURS MANDIRI
Route::get('kurs-mandiri', [App\Http\Controllers\Api\Config\KursMandiriController::class, 'index']);
Route::get('kurs-mandiri/get/{id}', [App\Http\Controllers\Api\Config\KursMandiriController::class, 'show']);
Route::post('kurs-mandiri/date', [App\Http\Controllers\Api\Config\KursMandiriController::class, 'indexDate']);
Route::get('kurs-mandiri/latest', [App\Http\Controllers\Api\Config\KursMandiriController::class, 'indexLatest']);

//Jenis Rekening
Route::get('jenis-rekening', [App\Http\Controllers\Api\Config\MasterJenisRekeningController::class, 'index']);
Route::get('jenis-rekening/get/{id}', [App\Http\Controllers\Api\Config\MasterJenisRekeningController::class, 'show']);

//Tipe Rekening
Route::get('tipe-rekening', [App\Http\Controllers\Api\Config\MasterTipeRekeningController::class, 'index']);
Route::get('tipe-rekening/get/{id}', [App\Http\Controllers\Api\Config\MasterTipeRekeningController::class, 'show']);
//Rekening
Route::get('rekening', [App\Http\Controllers\Api\Config\MasterRekeningController::class, 'index']);
Route::get('rekening/get/{id}', [App\Http\Controllers\Api\Config\MasterRekeningController::class, 'show']);
//Rekening Unit Kerja
Route::get('rekening-unit', [App\Http\Controllers\Api\Cash\RekeningUnitKerjaController::class, 'index']);
Route::get('rekening-unit/get/{id}', [App\Http\Controllers\Api\Cash\RekeningUnitKerjaController::class, 'show']);
Route::get('rekening-unit/latest', [App\Http\Controllers\Api\Cash\RekeningUnitKerjaController::class, 'indexLatest']);
Route::get('rekening-unit/tipe/{id}', [App\Http\Controllers\Api\Cash\RekeningUnitKerjaController::class, 'indexTipe']);

//Master Product
Route::get('product', [App\Http\Controllers\Api\Config\MasterProductController::class, 'index']);
Route::get('product/get/{id}', [App\Http\Controllers\Api\Config\MasterProductController::class, 'show']);
Route::get('product/productable/{id}/{type}', [App\Http\Controllers\Api\Config\MasterProductController::class, 'showProductable']);

//Master Sub Product
Route::get('sub-product', [App\Http\Controllers\Api\Config\MasterSubProductController::class, 'index']);
Route::get('sub-product/get/{id}', [App\Http\Controllers\Api\Config\MasterSubProductController::class, 'show']);
Route::get('sub-product/product/{id}', [App\Http\Controllers\Api\Config\MasterSubProductController::class, 'indexProdId']);

//Master Retail Produksi
Route::get('retail', [App\Http\Controllers\Api\Config\MasterRetailController::class, 'index']);
Route::get('retail/get/{id}', [App\Http\Controllers\Api\Config\MasterRetailController::class, 'show']);

//Intial Supply
Route::get('initial-supply', [App\Http\Controllers\Api\AvgPrice\InitialSupplyController::class, 'index']);
Route::get('initial-supply/get/{id}', [App\Http\Controllers\Api\AvgPrice\InitialSupplyController::class, 'show']);
Route::post('initial-supply/date', [App\Http\Controllers\Api\AvgPrice\InitialSupplyController::class, 'indexDate']);
Route::post('initial-supply/recap', [App\Http\Controllers\Api\AvgPrice\InitialSupplyController::class, 'indexRecap']);

//Location
Route::get('location', [App\Http\Controllers\Api\Config\LocationController::class, 'index']);
Route::get('location/get/{id}', [App\Http\Controllers\Api\Config\LocationController::class, 'show']);
//Master Sub Product
Route::get('tank', [App\Http\Controllers\Api\Config\TankController::class, 'index']);
Route::get('tank/get/{id}', [App\Http\Controllers\Api\Config\TankController::class, 'show']);
Route::get('tank/location/{id}', [App\Http\Controllers\Api\Config\TankController::class, 'indexLoctId']);
//StockBulky
Route::get('stock-bulky', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'index']);
Route::get('stock-bulky/get/{id}', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'show']);
Route::post('stock-bulky/date', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'indexDate']);
Route::post('stock-bulky/period', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'indexPeriod']);
Route::get('stock-bulky/latest', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'indexLatest']);
//Kapasitas WH Pallet
Route::get('kapasitas-wh-pallet', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'index']);
Route::get('kapasitas-wh-pallet/get/{id}', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'show']);
Route::post('kapasitas-wh-pallet/date', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'indexDate']);
Route::post('kapasitas-wh-pallet/period', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'indexPeriod']);
Route::get('kapasitas-wh-pallet/latest', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'indexLatest']);
//StockRetail
Route::get('stock-retail', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'index']);
Route::get('stock-retail/get/{id}', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'show']);
Route::post('stock-retail/date', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'indexDate']);
Route::post('stock-retail/period', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'indexPeriod']);
Route::get('stock-retail/latest', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'indexLatest']);

//Procost
Route::post('market-value/date', [App\Http\Controllers\Api\ProCost\ProcostController::class, 'indexDate']);

//CostingHPP
Route::post('costing-hpp/date', [App\Http\Controllers\Api\CostingHPP\CostingHppController::class, 'indexPeriod']);
//penyusutan
Route::get('biaya-penyusutan', [App\Http\Controllers\Api\Penyusutan\BiayaPenyusutanController::class, 'index']);
Route::get('biaya-penyusutan/get/{id}', [App\Http\Controllers\Api\Penyusutan\BiayaPenyusutanController::class, 'show']);
Route::get('biaya-penyusutan/latest', [App\Http\Controllers\Api\Penyusutan\BiayaPenyusutanController::class, 'indexLatest']);

Route::group(['middleware' => 'levelone.checker'], function () {
    //Kategori Uraian Produksi
    Route::post('kategori-produksi/add', [App\Http\Controllers\Api\Config\KategoriUraianProduksiController::class, 'store']);
    Route::post('kategori-produksi/update/{id}', [App\Http\Controllers\Api\Config\KategoriUraianProduksiController::class, 'update']);
    //Laporan Produksi
    Route::post('laporan-produksi/add', [App\Http\Controllers\Api\DetAlloc\LaporanProduksiController::class, 'store']);
    Route::post('laporan-produksi/update/{id}', [App\Http\Controllers\Api\DetAlloc\LaporanProduksiController::class, 'update']);
    //Daily DMO
    Route::post('daily-dmo/add', [App\Http\Controllers\Api\Target\DailyDmoController::class, 'store']);
    Route::post('daily-dmo/update/{id}', [App\Http\Controllers\Api\Target\DailyDmoController::class, 'update']);
    //Monthly DMO
    Route::post('monthly-dmo/add', [App\Http\Controllers\Api\Target\MonthlyDmoController::class, 'store']);
    Route::post('monthly-dmo/update/{id}', [App\Http\Controllers\Api\Target\MonthlyDmoController::class, 'update']);
});

Route::group(['middleware' => 'levelnine.checker'], function () {
    //penyusutan
    Route::post('biaya-penyusutan/add', [App\Http\Controllers\Api\Penyusutan\BiayaPenyusutanController::class, 'store']);
    Route::post('biaya-penyusutan/update/{id}', [App\Http\Controllers\Api\Penyusutan\BiayaPenyusutanController::class, 'update']);
    //harga satuan produksi
    Route::post('harga-satuan/add', [App\Http\Controllers\Api\Config\HargaSatuanProduksiController::class, 'store']);
    //allocation
    Route::post('allocation/add', [App\Http\Controllers\Api\Config\AllocationController::class, 'store']);
    Route::post('allocation/update/{id}', [App\Http\Controllers\Api\Config\AllocationController::class, 'update']);
    //cCenter
    Route::post('cCenter/add', [App\Http\Controllers\Api\Config\cCentreController::class, 'store']);
    Route::post('cCenter/update/{id}', [App\Http\Controllers\Api\Config\cCentreController::class, 'update']);
    //mReport
    Route::post('mReport/add', [App\Http\Controllers\Api\Config\mReportController::class, 'store']);
    Route::post('mReport/update/{id}', [App\Http\Controllers\Api\Config\mReportController::class, 'update']);
    //plant
    Route::post('plant/add', [App\Http\Controllers\Api\Config\PlantController::class, 'store']);
    Route::post('plant/update/{id}', [App\Http\Controllers\Api\Config\PlantController::class, 'update']);
    //category1
    Route::post('category/add', [App\Http\Controllers\Api\Config\Category1Controller::class, 'store']);
    Route::post('category/update/{id}', [App\Http\Controllers\Api\Config\Category1Controller::class, 'update']);
    //DB
    Route::post('debe/add', [App\Http\Controllers\Api\Debe\DebeController::class, 'store']);
    Route::post('debe/update/{id}', [App\Http\Controllers\Api\Debe\DebeController::class, 'update']);
    //master Bulky
    Route::post('bulky/add', [App\Http\Controllers\Api\Config\MasterBulkyController::class, 'store']);
    Route::post('bulky/update/{id}', [App\Http\Controllers\Api\Config\MasterBulkyController::class, 'update']);
    //master Bulky Produksi
    Route::post('bulky-prod/add', [App\Http\Controllers\Api\Config\MasterBulkProduksiController::class, 'store']);
    Route::post('bulky-prod/update/{id}', [App\Http\Controllers\Api\Config\MasterBulkProduksiController::class, 'update']);
    //master Retail Produksi
    Route::post('retail-prod/add', [App\Http\Controllers\Api\Config\MasterRetailProduksiController::class, 'store']);
    Route::post('retail-prod/update/{id}', [App\Http\Controllers\Api\Config\MasterRetailProduksiController::class, 'update']);
    //setting
    Route::post('settings/add', [App\Http\Controllers\Api\Config\SettingController::class, 'store']);
    Route::post('settings/update/{id}', [App\Http\Controllers\Api\Config\SettingController::class, 'update']);
    //Target Real
    Route::post('target-real/add', [App\Http\Controllers\Api\Target\TargetRealController::class, 'store']);
    Route::post('target-real/update/{id}', [App\Http\Controllers\Api\Target\TargetRealController::class, 'update']);
    //Target RKAP
    Route::post('target-rkap/add', [App\Http\Controllers\Api\Target\TargetRkapController::class, 'store']);
    Route::post('target-rkap/update/{id}', [App\Http\Controllers\Api\Target\TargetRkapController::class, 'update']);
    //Kurs Mandiri
    Route::post('kurs-mandiri/add', [App\Http\Controllers\Api\Config\KursMandiriController::class, 'store']);
    Route::post('kurs-mandiri/update/{id}', [App\Http\Controllers\Api\Config\KursMandiriController::class, 'update']);
    //Jenis Rekening
    Route::post('jenis-rekening/add', [App\Http\Controllers\Api\Config\MasterJenisRekeningController::class, 'store']);
    Route::post('jenis-rekening/update/{id}', [App\Http\Controllers\Api\Config\MasterJenisRekeningController::class, 'update']);
    //Tipe Rekening
    Route::post('tipe-rekening/add', [App\Http\Controllers\Api\Config\MasterTipeRekeningController::class, 'store']);
    Route::post('tipe-rekening/update/{id}', [App\Http\Controllers\Api\Config\MasterTipeRekeningController::class, 'update']);
    //Rekening
    Route::post('rekening/add', [App\Http\Controllers\Api\Config\MasterRekeningController::class, 'store']);
    Route::post('rekening/update/{id}', [App\Http\Controllers\Api\Config\MasterRekeningController::class, 'update']);
    //Rekening
    Route::post('rekening-unit/add', [App\Http\Controllers\Api\Cash\RekeningUnitKerjaController::class, 'store']);
    Route::post('rekening-unit/update/{id}', [App\Http\Controllers\Api\Cash\RekeningUnitKerjaController::class, 'update']);
    //Master Product
    Route::post('product/add', [App\Http\Controllers\Api\Config\MasterProductController::class, 'store']);
    Route::post('product/update/{id}', [App\Http\Controllers\Api\Config\MasterProductController::class, 'update']);
    //Master Product
    Route::post('sub-product/add', [App\Http\Controllers\Api\Config\MasterSubProductController::class, 'store']);
    Route::post('sub-product/update/{id}', [App\Http\Controllers\Api\Config\MasterSubProductController::class, 'update']);
    //master Retail Produksi
    Route::post('retail/add', [App\Http\Controllers\Api\Config\MasterRetailController::class, 'store']);
    Route::post('retail/update/{id}', [App\Http\Controllers\Api\Config\MasterRetailController::class, 'update']);

    //Intial Supply
    Route::post('initial-supply/add', [App\Http\Controllers\Api\AvgPrice\InitialSupplyController::class, 'store']);
    Route::post('initial-supply/update/{id}', [App\Http\Controllers\Api\AvgPrice\InitialSupplyController::class, 'update']);

    //location
    Route::post('location/add', [App\Http\Controllers\Api\Config\LocationController::class, 'store']);
    Route::post('location/update/{id}', [App\Http\Controllers\Api\Config\LocationController::class, 'update']);

    //tank
    Route::post('tank/add', [App\Http\Controllers\Api\Config\TankController::class, 'store']);
    Route::post('tank/update/{id}', [App\Http\Controllers\Api\Config\TankController::class, 'update']);

    //StokBulky
    Route::post('stock-bulky/add', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'store']);
    Route::post('stock-bulky/update/{id}', [App\Http\Controllers\Api\Stock\StokBulkyController::class, 'update']);
    //Kapasitas WH Pallet
    Route::post('kapasitas-wh-pallet/add', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'store']);
    Route::post('kapasitas-wh-pallet/update/{id}', [App\Http\Controllers\Api\Stock\KapasitasWHPalletController::class, 'update']);
    //StokRetail
    Route::post('stock-retail/add', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'store']);
    Route::post('stock-retail/update/{id}', [App\Http\Controllers\Api\Stock\StokRetailController::class, 'update']);
});

Route::group(['middleware' => 'levelseven.checker'], function () {
    //LevyDuty
    Route::post('levy-duty/add', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'store']);
    Route::post('levy-duty/update/{id}', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'update']);
});

Route::group(['middleware' => 'levelsix.checker'], function () {
    //MarketRouters
    Route::post('market-router/add', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'store']);
    Route::post('market-router/update/{id}', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'update']);
});

Route::group(['middleware' => 'levelfive.checker'], function () {
    //Actual
    Route::post('actual-cpo/add', [App\Http\Controllers\Api\CPO\ActualController::class, 'store']);
    Route::post('actual-cpo/update/{id}', [App\Http\Controllers\Api\CPO\ActualController::class, 'update']);
    //Outstanding
    Route::post('outstanding-cpo/add', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'store']);
    Route::post('outstanding-cpo/update/{id}', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'update']);
    //KPBN
    Route::post('cpo-kpbn/add', [App\Http\Controllers\Api\CPO\KpbnController::class, 'store']);
    Route::post('cpo-kpbn/update/{id}', [App\Http\Controllers\Api\CPO\KpbnController::class, 'update']);
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::fallback(function () {
    return response()->json(['code' => 404, 'message' => 'URL not Found'], 404);
});
