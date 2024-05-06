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

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::group(['middleware' => 'levelnine.checker'], function () {
    //allocation
    Route::get('allocation', [App\Http\Controllers\Api\Config\AllocationController::class, 'index']);
    Route::get('allocation/get/{id}', [App\Http\Controllers\Api\Config\AllocationController::class, 'show']);
    Route::post('allocation/add', [App\Http\Controllers\Api\Config\AllocationController::class, 'store']);
    Route::post('allocation/update/{id}', [App\Http\Controllers\Api\Config\AllocationController::class, 'update']);
    //cCenter
    Route::get('cCenter', [App\Http\Controllers\Api\Config\cCentreController::class, 'index']);
    Route::get('cCenter/get/{id}', [App\Http\Controllers\Api\Config\cCentreController::class, 'show']);
    Route::post('cCenter/add', [App\Http\Controllers\Api\Config\cCentreController::class, 'store']);
    Route::post('cCenter/update/{id}', [App\Http\Controllers\Api\Config\cCentreController::class, 'update']);
    //mReport
    Route::get('mReport', [App\Http\Controllers\Api\Config\mReportController::class, 'index']);
    Route::get('mReport/get/{id}', [App\Http\Controllers\Api\Config\mReportController::class, 'show']);
    Route::post('mReport/add', [App\Http\Controllers\Api\Config\mReportController::class, 'store']);
    Route::post('mReport/update/{id}', [App\Http\Controllers\Api\Config\mReportController::class, 'update']);
    //plant
    Route::get('plant', [App\Http\Controllers\Api\Config\PlantController::class, 'index']);
    Route::get('plant/get/{id}', [App\Http\Controllers\Api\Config\PlantController::class, 'show']);
    Route::post('plant/add', [App\Http\Controllers\Api\Config\PlantController::class, 'store']);
    Route::post('plant/update/{id}', [App\Http\Controllers\Api\Config\PlantController::class, 'update']);
    //category1
    Route::get('category1', [App\Http\Controllers\Api\Config\Category1Controller::class, 'index']);
    Route::get('category1/get/{id}', [App\Http\Controllers\Api\Config\Category1Controller::class, 'show']);
    Route::post('category/add', [App\Http\Controllers\Api\Config\Category1Controller::class, 'store']);
    Route::post('category/update/{id}', [App\Http\Controllers\Api\Config\Category1Controller::class, 'update']);
    //category2
    Route::get('category2/c1/{cat1}', [App\Http\Controllers\Api\Config\Category2Controller::class, 'indexCat1']);
    Route::get('category2/get/{id}', [App\Http\Controllers\Api\Config\Category2Controller::class, 'show']);
    //category3
    Route::get('category3/c2/{cat2}', [App\Http\Controllers\Api\Config\Category3Controller::class, 'indexCat2']);
    Route::get('category3/get/{id}', [App\Http\Controllers\Api\Config\Category3Controller::class, 'show']);
    //DB
    Route::get('debe', [App\Http\Controllers\Api\Debe\DebeController::class, 'index']);
    Route::get('debe/get/{id}', [App\Http\Controllers\Api\Debe\DebeController::class, 'show']);
    Route::post('debe/add', [App\Http\Controllers\Api\Debe\DebeController::class, 'store']);
    Route::post('debe/update/{id}', [App\Http\Controllers\Api\Debe\DebeController::class, 'update']);

});

Route::group(['middleware' => 'levelfive.checker'], function () {
    //Actual
    Route::get('actual-cpo', [App\Http\Controllers\Api\CPO\ActualController::class, 'index']);
    Route::get('actual-cpo/get/{id}', [App\Http\Controllers\Api\CPO\ActualController::class, 'show']);
    Route::post('actual-cpo/add', [App\Http\Controllers\Api\CPO\ActualController::class, 'store']);
    Route::post('actual-cpo/date', [App\Http\Controllers\Api\CPO\ActualController::class, 'indexDate']);
    Route::post('actual-cpo/update/{id}', [App\Http\Controllers\Api\CPO\ActualController::class, 'update']);
    //Outstanding
    Route::get('outstanding-cpo', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'index']);
    Route::get('outstanding-cpo/get/{id}', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'show']);
    Route::post('outstanding-cpo/add', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'store']);
    Route::post('outstanding-cpo/update/{id}', [App\Http\Controllers\Api\CPO\OutstandingController::class, 'update']);
    //KPBN
    Route::get('cpo-kpbn', [App\Http\Controllers\Api\CPO\KpbnController::class, 'index']);
    Route::get('cpo-kpbn/get/{id}', [App\Http\Controllers\Api\CPO\KpbnController::class, 'show']);
    Route::post('cpo-kpbn/add', [App\Http\Controllers\Api\CPO\KpbnController::class, 'store']);
    Route::post('cpo-kpbn/date', [App\Http\Controllers\Api\CPO\KpbnController::class, 'indexDate']);
    Route::post('cpo-kpbn/update/{id}', [App\Http\Controllers\Api\CPO\KpbnController::class, 'update']);

});


Route::fallback(function () {
    return response()->json(['code' => 404, 'message' => 'URL not Found'], 404);
});
