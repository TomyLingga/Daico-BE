<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\UraianProduksi;
use Illuminate\Http\Request;

class UraianProduksiController extends Controller
{
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';

    public function index()
    {
        try {
            $subGroups = UraianProduksi::with('kategori')
                            ->orderBy('id_category', 'asc')
                            ->orderBy('id', 'asc')
                            ->get();

            if ($subGroups->isEmpty()) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            return response()->json([
                'data' => $subGroups,
                'message' => $this->messageAll,
                'success' => true,
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $ex) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $ex->getTrace()[0],
                'errMsg' => $ex->getMessage(),
                'success' => false,
                'code' => 500
            ], 500);
        }
    }

    public function indexGrup($id)
    {
        try {
            $subGroups = UraianProduksi::where('id_category', $id)->with('kategori')->orderBy('id', 'asc')->get();

            if ($subGroups->isEmpty()) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            return response()->json([
                'data' => $subGroups,
                'message' => $this->messageAll,
                'success' => true,
                'code' => 200
            ], 200);
        } catch (\Illuminate\Database\QueryException $ex) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $ex->getTrace()[0],
                'errMsg' => $ex->getMessage(),
                'success' => false,
                'code' => 500
            ], 500);
        }
    }


    public function show($id)
    {
        try {
            $subGroup = UraianProduksi::with('kategori')->find($id);

            if (!$subGroup) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            return response()->json([
                'data' => $subGroup,
                'message' => $this->messageSuccess,
                'success' => true,
                'code' => 200,
            ], 200);
        } catch (\Illuminate\Database\QueryException $ex) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $ex->getTrace()[0],
                'errMsg' => $ex->getMessage(),
                'success' => false,
                'code' => 500,
            ], 500);
        }
    }
}
