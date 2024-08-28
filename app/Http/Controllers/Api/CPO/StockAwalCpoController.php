<?php

namespace App\Http\Controllers\Api\CPO;

use App\Http\Controllers\Controller;
use App\Models\actualIncomingCpo;
use App\Models\StockAwalCpo;
use App\Services\LoggerService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class StockAwalCpoController extends Controller
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
                'qty' => 'required|numeric',
                'harga' => 'required|numeric'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    // 'code' => 400,
                    'success' => false,
                ], 400);
            }

            $existingRecord = StockAwalCpo::whereYear('tanggal', '=', date('Y', strtotime($request->input('tanggal'))))
                ->whereMonth('tanggal', '=', date('m', strtotime($request->input('tanggal'))))
                ->exists();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'Data already exists for this month.',
                    'success' => false,
                ], 400);
            }

            $data = StockAwalCpo::create($request->all());

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
            $data = StockAwalCpo::orderBy('tanggal')->get();

            if ($data->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            return response()->json(['data' => $data, 'message' => $this->messageAll], 200);
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

    public function indexDate(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $dataStockAwal = StockAwalCpo::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('tanggal')
                ->first();

            if (is_null($dataStockAwal)) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $dataStockAwal->value = $dataStockAwal->qty * $dataStockAwal->harga;

            $dataIncoming = actualIncomingCpo::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
                ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
                ->orderBy('tanggal')
                ->get();

            if ($dataIncoming->isEmpty()) {
                return response()->json(['message' => $this->messageMissing], 401);
            }

            $totalQty = 0;
            $totalValue = 0;
            $latestTanggal = null;

            $dataIncoming->transform(function ($item) use (&$totalQty, &$totalValue, &$latestTanggal) {
                $item['value'] = $item->qty * $item->harga;
                $totalQty += $item->qty;
                $totalValue += $item['value'];

                if (is_null($latestTanggal) || $item->tanggal > $latestTanggal) {
                    $latestTanggal = $item->tanggal;
                }

                return $item;
            });

            $totalHarga = ($totalQty > 0) ? $totalValue / $totalQty : 0;

            $stokTersediaQty = $dataStockAwal->qty + $totalQty;
            $stokTersediaValue = $dataStockAwal->value + $totalValue;
            $stokTersediaHarga = ($stokTersediaQty > 0) ? $stokTersediaValue / $stokTersediaQty : 0;


            $dataLaporanProduksi = $this->indexLaporanProduksi($request);
            $qtyCpoOlah = $dataLaporanProduksi['laporanProduksi'][0]['uraian'][0]['total_qty'];
            $hargaCpoOlah = $stokTersediaHarga;
            $valueCpoOlah = $qtyCpoOlah * $hargaCpoOlah;

            return response()->json([
                'dataStockAwal' => $dataStockAwal,
                'dataIncoming' => [
                    'latestDate' => $latestTanggal,
                    'totalQty' => $totalQty,
                    'totalHarga' => $totalHarga,
                    'totalValue' => $totalValue
                ],
                'stokTersedia' => [
                    'totalQty' => $stokTersediaQty,
                    'totalHarga' => $stokTersediaHarga,
                    'totalValue' => $stokTersediaValue
                ],
                'cpoOlah' => [
                    'totalQty' => $qtyCpoOlah,
                    'totalHarga' => $hargaCpoOlah,
                    'totalValue' => $valueCpoOlah
                ],
                'message' => $this->messageAll,
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

    public function show($id)
    {
        try {
            $data = StockAwalCpo::findOrFail($id);

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

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {

            $rules = [
                'tanggal' => 'required|date',
                'qty' => 'required|numeric',
                'harga' => 'required|numeric'
            ];

            $validator = Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                return response()->json([
                    'message' => $validator->errors(),
                    'success' => false
                ], 400);
            }

            $data = StockAwalCpo::findOrFail($id);

            $existingRecord = StockAwalCpo::whereYear('tanggal', '=', date('Y', strtotime($request->input('tanggal'))))
                ->whereMonth('tanggal', '=', date('m', strtotime($request->input('tanggal'))))
                ->where('id', '!=', $id)
                ->exists();

            if ($existingRecord) {
                return response()->json([
                    'message' => 'Data already exists for this month.',
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
}
