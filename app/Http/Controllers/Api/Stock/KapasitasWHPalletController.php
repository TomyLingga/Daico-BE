<?php

namespace App\Http\Controllers\Api\Stock;

use App\Http\Controllers\Controller;
use App\Models\KapasitasWhPallet;
use App\Models\Location;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class KapasitasWHPalletController extends Controller
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
            $rules = [
                'location_id' => 'required|exists:' . Location::class . ',id',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $existingEntry = KapasitasWhPallet::where('location_id', $request->location_id)
                ->whereDate('tanggal', $request->tanggal)
                ->first();

            if ($existingEntry) {
                return response()->json([
                    'message' => 'An entry already exists for this location on the specified date.',
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $data = KapasitasWhPallet::create($request->all());


            LoggerService::logAction($this->userData, $data, 'create', null, $data->toArray());

            DB::commit();

            return response()->json([
                'data' => $data,
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
                'location_id' => 'required|exists:' . Location::class . ',id',
                'tanggal' => 'required|date',
                'value' => 'required|numeric',
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false,
                ], 400);
            }

            $data = KapasitasWhPallet::findOrFail($id);

            $existingEntry = KapasitasWhPallet::where('location_id', $request->location_id)
                ->whereDate('tanggal', $request->tanggal)
                ->where('id', '!=', $id)
                ->first();

            if ($existingEntry) {
                return response()->json([
                    'message' => 'An entry already exists for this location on the specified date.',
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $oldData = $data->toArray();

            $data->update($request->all());

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

            $data = KapasitasWhPallet::with('location')
                ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->get();

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

            $data = KapasitasWhPallet::with('location')
                ->where('tanggal', $request->tanggal)
                ->get();

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
            $data = KapasitasWhPallet::with('location')->get();

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
            $subquery = KapasitasWhPallet::select('location_id', DB::raw('MAX(tanggal) as max_tanggal'))
                ->groupBy('location_id');

            $data = KapasitasWhPallet::with('location')
                ->joinSub($subquery, 'latest_entries', function ($join) {
                    $join->on('kapasitas_wh_pallet.location_id', '=', 'latest_entries.location_id')
                        ->on('kapasitas_wh_pallet.tanggal', '=', 'latest_entries.max_tanggal');
                })
                ->get();

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
            $data = KapasitasWhPallet::with('location')
                                ->findOrFail($id);

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
