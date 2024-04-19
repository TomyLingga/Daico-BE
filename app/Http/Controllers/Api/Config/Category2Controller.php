<?php

namespace App\Http\Controllers\Api\Config;

use App\Http\Controllers\Controller;
use App\Models\Category2;
use Illuminate\Http\Request;

class Category2Controller extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';

    public function indexCat1($cat1)
    {
        try {
            $category2 = Category2::where('id_category1', $cat1)->get();
            // $category2 = Category2::with([
            //     'cat3'
            //     ])->where('id_category1', $cat1)
            //     ->get();

            if ($category2->isEmpty()) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            return response()->json([
                'data' => $category2,
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
            $category2 = Category2::findOrFail($id);
            // $category2 = Category2::with([
            //     'cat3'
            //     ])->where('id_category1', $cat1)
            //     ->get();

            if ($category2->isEmpty()) {

                return response()->json([
                    'message' => $this->messageMissing,
                    'success' => true,
                    'code' => 401
                ], 401);
            }

            return response()->json([
                'data' => $category2,
                'message' => $this->messageSuccess,
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
}
