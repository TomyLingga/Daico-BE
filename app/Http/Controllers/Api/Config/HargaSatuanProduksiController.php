<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\HargaSatuanProduksi;
use App\Models\UraianProduksi;
use App\Services\LoggerService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HargaSatuanProduksiController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    // private $messageUpdate = 'Success to Update Data';

    public function store(Request $request)
    {
        DB::beginTransaction();

        try {
            $validator = Validator::make($request->all(), [
                'id_uraian_produksi' => 'required|integer',
                'value' => 'required|numeric',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }
            UraianProduksi::findOrFail($request->id_uraian_produksi);

            $data = HargaSatuanProduksi::create($request->all());

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

    public function index()
    {
        try {
            $data = HargaSatuanProduksi::with('uraian')->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data,'message' => $this->messageAll], 200);
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

    public function indexLatest()
    {
        try {
            // Subquery to get the latest ID for each id_uraian_produksi
            $latestIds = HargaSatuanProduksi::select('id_uraian_produksi', DB::raw('MAX(id) as latest_id'))
                ->groupBy('id_uraian_produksi');

            // Join with the original table to get the latest records
            $data = HargaSatuanProduksi::joinSub($latestIds, 'latest', function ($join) {
                    $join->on('harga_satuan_produksi.id', '=', 'latest.latest_id');
                })
                ->with('uraian') // Eager load the uraian relationship
                ->orderBy('harga_satuan_produksi.id_uraian_produksi')
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
            $data = HargaSatuanProduksi::with('uraian')->findOrFail($id);

            // $data['history'] = $this->formatLogs($data->logs);
            // unset($data->logs);


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
