<?php

namespace App\Http\Controllers\Api\GL;

use App\Http\Controllers\Controller;
use App\Models\Debe;
use Carbon\Carbon;
use Illuminate\Http\Request;

class GeneralLedgerController extends Controller
{
    public function index_period(Request $request)
    {
        $tanggal = $request->get('tanggal');
        $date = Carbon::parse($tanggal);
        $debe = Debe::with('cat3', 'mReport', 'cCentre', 'plant', 'allocation')->get();
        $coa = Debe::pluck('coa');
        $glData = $this->getGeneralLedgerData($date);
        $glDataCollection = collect($glData);

        $filteredGlData = $glDataCollection->filter(function ($item) use ($coa) {
            return in_array($item['account_account']['code'], $coa->toArray());
        });

        $totalDebit = $filteredGlData->sum(function ($item) {
            return $item['debit'];
        });

        $totalCredit = $filteredGlData->sum(function ($item) {
            return $item['credit'];
        });

        $totalDifference = $totalDebit - $totalCredit;

        $filteredGlData->transform(function ($item) use ($debe) {
            $coaCode = $item['account_account']['code'];
            $debeModel = $debe->firstWhere('coa', $coaCode);
            $item['debe'] = $debeModel;
            return $item;
        });

        if ($filteredGlData->isEmpty()) {
            return response()->json([
                'message' => "No Data Found",
                'success' => false,
                'code' => 404
            ], 404);
        }

        return response()->json([
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'totalDifference' => $totalDifference,
            'data' => $filteredGlData->values(),
            'message' => 'Data Retrieved Successfully',
            'code' => 200,
            'success' => true,
        ], 200);
    }

}
