<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\MasterProduct;
use App\Models\MasterRetail;
use App\Models\MasterSubProduct;
use App\Models\Setting;
use App\Models\StokRetail;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StokRetailController extends Controller
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
                'tanggal' => 'required|date',
                'location_id' => 'required|exists:' . Location::class . ',id',
                'productable_id' => 'required|integer',
                'productable_type' => 'required|string|in:retail,product,subproduct',
                'ctn' => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $productableType = null;
            if ($request->productable_type === 'retail') {
                MasterRetail::findOrFail($request->productable_id);
                $productableType = MasterRetail::class;
            } else if ($request->productable_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->productable_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $existingEntry = StokRetail::where('tanggal', $request->tanggal)
                                  ->where('location_id', $request->location_id)
                                  ->where('productable_id', $request->location_id)
                                  ->where('productable_type', $productableType)
                                  ->first();
            if ($existingEntry) {
                return response()->json([
                    'message' => 'An entry for this location with product and on the given date already exists.',
                    'success' => false,
                ], 400);
            }

            $real = new StokRetail();
            $real->tanggal = $request->tanggal;
            $real->location_id = $request->location_id;
            $real->productable_id = $request->productable_id;
            $real->productable_type = $productableType;
            $real->ctn = $request->ctn;

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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'tanggal' => 'required|date',
                'location_id' => 'required|exists:' . Location::class . ',id',
                'productable_id' => 'required|integer',
                'productable_type' => 'required|string|in:retail,product,subproduct',
                'ctn' => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $productableType = null;
            if ($request->productable_type === 'bulk') {
                MasterRetail::findOrFail($request->productable_id);
                $productableType = MasterRetail::class;
            } else if ($request->productable_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->productable_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $existingEntry = StokRetail::where('tanggal', $request->tanggal)
                                  ->where('location_id', $request->location_id)
                                  ->where('productable_id', $request->location_id)
                                  ->where('productable_type', $productableType)
                                  ->where('id', '!=', $id)
                                  ->first();
            if ($existingEntry) {
                return response()->json([
                    'message' => 'An entry for this location with product and on the given date already exists.',
                    'success' => false,
                ], 400);
            }

            $data = StokRetail::findOrFail($id);
            $oldData = $data->toArray();

            $data->tanggal = $request->tanggal;
            $data->location_id = $request->location_id;
            $data->productable_id = $request->productable_id;
            $data->productable_type = $productableType;
            $data->ctn = $request->ctn;
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

    public function indexPeriod(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $data = StokRetail::with('productable','location')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
            });

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $settingNames = ['pembagi_pallet_stok_retail',
                            'pengali_kapasitas_wh_ctn',
                            'pengali_kapasitas_wh_mt',
                            'pengali_mt_stok_retail',
                            'pembagi_mt_stok_retail',
                            'ctn_mt_250ml',
                            'ctn_mt_450ml',
                            'ctn_mt_900ml',
                            'ctn_mt_1800ml',
                            'ctn_mt_1l',
                            'ctn_mt_2l',
                            ];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            if ($settings->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data, 'settings' => $settings,'message' => $this->messageAll], 200);
        } catch (QueryException $e) {
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

            $data = StokRetail::with('productable','location')
                ->where('tanggal', $request->tanggal)
                ->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
            });

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $settingNames = ['pembagi_pallet_stok_retail',
                            'pengali_kapasitas_wh_ctn',
                            'pengali_kapasitas_wh_mt',
                            'pengali_mt_stok_retail',
                            'pembagi_mt_stok_retail',
                            'ctn_mt_250ml',
                            'ctn_mt_450ml',
                            'ctn_mt_900ml',
                            'ctn_mt_1800ml',
                            'ctn_mt_1l',
                            'ctn_mt_2l',
                            ];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            if ($settings->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data, 'settings' => $settings, 'message' => $this->messageAll], 200);
        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function indexLatest()
    {
        try {
            // Subquery to get the latest tanggal for each tank
            $subquery = StokRetail::select('location_id', DB::raw('MAX(tanggal) as max_tanggal'))
                ->groupBy('location_id');

            // Join the subquery to get the latest entries
            $data = StokRetail::with('productable', 'location')
                ->joinSub($subquery, 'latest_entries', function($join) {
                    $join->on('stok_retail.location_id', '=', 'latest_entries.location_id')
                        ->on('stok_retail.tanggal', '=', 'latest_entries.max_tanggal');
                })
                ->get();

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

    public function index()
    {
        try {
            $data = StokRetail::with('productable','location')->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
            });

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $settingNames = ['pembagi_pallet_stok_retail',
                            'pengali_kapasitas_wh_ctn',
                            'pengali_kapasitas_wh_mt',
                            'pengali_mt_stok_retail',
                            'pembagi_mt_stok_retail',
                            'ctn_mt_250ml',
                            'ctn_mt_450ml',
                            'ctn_mt_900ml',
                            'ctn_mt_1800ml',
                            'ctn_mt_1l',
                            'ctn_mt_2l',
                            ];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            if ($settings->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data, 'settings' => $settings, 'message' => $this->messageAll], 200);
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
            $data = StokRetail::with('productable','location')
                                ->findOrFail($id);
            $data->makeHidden('productable');
            $data->extended_productable;

            $settingNames = ['pembagi_pallet_stok_retail',
                            'pengali_kapasitas_wh_ctn',
                            'pengali_kapasitas_wh_mt',
                            'pengali_mt_stok_retail',
                            'pembagi_mt_stok_retail',
                            'ctn_mt_250ml',
                            'ctn_mt_450ml',
                            'ctn_mt_900ml',
                            'ctn_mt_1800ml',
                            'ctn_mt_1l',
                            'ctn_mt_2l',
                            ];
            $settings = Setting::whereIn('setting_name', $settingNames)->get();

            if ($settings->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }
            return response()->json([
                'data' => $data,
                'settings' => $settings,
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
