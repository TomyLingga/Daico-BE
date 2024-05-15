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
Route::get('settings/name/{name}', [App\Http\Controllers\Api\Config\MasterBulkProduksiController::class, 'showName']);

Route::group(['middleware' => 'levelone.checker'], function () {
    //General Ledger
    Route::post('general-ledger/date', [App\Http\Controllers\Api\GL\GeneralLedgerController::class, 'index_period']);
});

Route::group(['middleware' => 'levelnine.checker'], function () {
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
    //setting
    Route::post('settings/add', [App\Http\Controllers\Api\Config\SettingController::class, 'store']);
    Route::post('settings/update/{id}', [App\Http\Controllers\Api\Config\SettingController::class, 'update']);

});

Route::group(['middleware' => 'levelsix.checker'], function () {
    //MarketRouters
    Route::post('market-router/add', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'store']);
    Route::post('market-router/update/{id}', [App\Http\Controllers\Api\ProCost\MarketRoutersController::class, 'update']);
});

Route::group(['middleware' => 'levelseven.checker'], function () {
    //LevyDuty
    Route::post('levy-duty/add', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'store']);
    Route::post('levy-duty/update/{id}', [App\Http\Controllers\Api\ProCost\LevyDutyController::class, 'update']);
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
