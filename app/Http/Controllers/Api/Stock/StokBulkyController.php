<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\MasterBulky;
use App\Models\MasterProduct;
use App\Models\MasterSubProduct;
use App\Models\StokBulky;
use App\Models\Tank;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StokBulkyController extends Controller
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
                'tank_id' => 'required|exists:' . Tank::class . ',id',
                'productable_id' => 'required|integer',
                'productable_type' => 'required|string|in:bulk,product,subproduct',
                'stok_mt' => 'required|numeric',
                'stok_exc_btm_mt' => 'required|numeric',
                'umur' => 'required|numeric',
                'remarks' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $existingEntry = StokBulky::where('tanggal', $request->tanggal)
                                  ->where('tank_id', $request->tank_id)
                                  ->first();
            if ($existingEntry) {
                return response()->json([
                    'message' => 'An entry for this tank on the given date already exists.',
                    'success' => false,
                ], 400);
            }

            $productableType = null;
            if ($request->productable_type === 'bulk') {
                MasterBulky::findOrFail($request->productable_id);
                $productableType = MasterBulky::class;
            } else if ($request->productable_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->productable_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $real = new StokBulky();
            $real->tanggal = $request->tanggal;
            $real->tank_id = $request->tank_id;
            $real->productable_id = $request->productable_id;
            $real->productable_type = $productableType;
            $real->stok_mt = $request->stok_mt;
            $real->stok_exc_btm_mt = $request->stok_exc_btm_mt;
            $real->umur = $request->umur;
            $real->remarks = $request->remarks;

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
                'tank_id' => 'required|exists:' . Tank::class . ',id',
                'productable_id' => 'required|integer',
                'productable_type' => 'required|string|in:bulk,product,subproduct',
                'stok_mt' => 'required|numeric',
                'stok_exc_btm_mt' => 'required|numeric',
                'umur' => 'required|numeric',
                'remarks' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $existingEntry = StokBulky::where('tanggal', $request->tanggal)
                                  ->where('tank_id', $request->tank_id)
                                  ->where('id', '!=', $id)
                                  ->first();
            if ($existingEntry) {
                return response()->json([
                    'message' => 'An entry for this tank on the given date already exists.',
                    'success' => false,
                ], 400);
            }

            $productableType = null;
            if ($request->productable_type === 'bulk') {
                MasterBulky::findOrFail($request->productable_id);
                $productableType = MasterBulky::class;
            } else if ($request->productable_type === 'product') {
                MasterProduct::findOrFail($request->productable_id);
                $productableType = MasterProduct::class;
            } else if ($request->productable_type === 'subproduct') {
                MasterSubProduct::findOrFail($request->productable_id);
                $productableType = MasterSubProduct::class;
            }

            $data = StokBulky::findOrFail($id);
            $oldData = $data->toArray();


            $data->tanggal = $request->tanggal;
            $data->tank_id = $request->tank_id;
            $data->productable_id = $request->productable_id;
            $data->productable_type = $productableType;
            $data->stok_mt = $request->stok_mt;
            $data->stok_exc_btm_mt = $request->stok_exc_btm_mt;
            $data->umur = $request->umur;
            $data->remarks = $request->remarks;
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

            $data = StokBulky::with('productable','tank')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
                $item->space = $item->tank->capacity - $item->stok_mt;
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

    public function indexDate(Request $request)
    {
        try {

            $data = StokBulky::with('productable','tank')
                ->where('tanggal', $request->tanggal)
                ->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
                $item->space = $item->tank->capacity - $item->stok_mt;
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
            $data = StokBulky::with('productable','tank')->get();

            $data->each(function ($item) {
                $item->makeHidden('productable');
                $item->extended_productable;
                $item->space = $item->tank->capacity - $item->stok_mt;
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

    public function indexLatest()
    {
        try {
            $subquery = StokBulky::select('tank_id', DB::raw('MAX(tanggal) as max_tanggal'))
                ->groupBy('tank_id');

            $data = StokBulky::with('productable', 'tank')
                ->joinSub($subquery, 'latest_entries', function($join) {
                    $join->on('stok_bulky.tank_id', '=', 'latest_entries.tank_id')
                        ->on('stok_bulky.tanggal', '=', 'latest_entries.max_tanggal');
                })
                ->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $groupedData = $data->groupBy(function ($item) {
                if (isset($item->extended_productable['product']['productable'])) {
                    return $item->extended_productable['product']['productable']['name'];
                } elseif (isset($item->extended_productable['productable'])) {
                    return $item->extended_productable['productable']['name'];
                } elseif ($item->extended_productable) {
                    return $item->extended_productable['name'];
                } else {
                    return 'Unknown';
                }
            });

            $bulkyStock = [];
            $kapasitasTotal = 0;
            $stokMtTotal = 0;
            $stokExcBtmTotal = 0;
            $spaceTotal = 0;

            foreach ($groupedData as $name => $items) {
                $totalKapasitas = $items->sum(fn($item) => $item->tank->capacity);
                $totalStockMt = $items->sum('stok_mt');
                $totalStockExcBtm = $items->sum('stok_exc_btm_mt');
                $totalSpace = $items->sum(fn($item) => $item->tank->capacity - $item->stok_mt);

                $kapasitasTotal += $totalKapasitas;
                $stokMtTotal += $totalStockMt;
                $stokExcBtmTotal += $totalStockExcBtm;
                $spaceTotal += $totalSpace;

                $bulkyStock[] = [
                    'name' => $name,
                    'totalKapasitas' => $totalKapasitas,
                    'totalStockMt' => $totalStockMt,
                    'totalStockExcBtm' => $totalStockExcBtm,
                    'totalSpace' => $totalSpace,
                    'items' => $items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'tank_id' => $item->tank_id,
                            'tanggal' => $item->tanggal,
                            'productable_id' => $item->productable_id,
                            'productable_type' => $item->productable_type,
                            'stok_mt' => $item->stok_mt,
                            'stok_exc_btm_mt' => $item->stok_exc_btm_mt,
                            'umur' => $item->umur,
                            'remarks' => $item->remarks,
                            'created_at' => $item->created_at,
                            'updated_at' => $item->updated_at,
                            'space' => $item->tank->capacity - $item->stok_mt,
                            'extended_productable' => $item->extended_productable,
                            'tank' => $item->tank,
                        ];
                    }),
                ];
            }

            $grandTotal = [
                'kapasitasTotal' => $kapasitasTotal,
                'stokMtTotal' => $stokMtTotal,
                'stokExcBtmTotal' => $stokExcBtmTotal,
                'spaceTotal' => $spaceTotal,
            ];

            return response()->json([
                'data' => [
                    'GrandTotal' => $grandTotal,
                    'bulkyStock' => $bulkyStock,
                ],
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
            $data = StokBulky::with('productable','tank')
                                ->findOrFail($id);
            $data->makeHidden('productable');
            $data->extended_productable;
            $data->space = $data->tank->capacity - $data->stok_mt;

            $data['history'] = $this->formatLogs($data->logs);
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
}
