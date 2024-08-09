<?php

namespace App\Http\Controllers\Api\ProCost;

use App\Http\Controllers\Controller;
use App\Models\cpoKpbn;
use App\Models\LevyDutyBulky;
use App\Models\MarketRoutersBulky;
use App\Models\Setting;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class ProcostController extends Controller
{
    private $messageFail = 'Something went wrong';
    private $messageMissing = 'Data not found in record';
    private $messageAll = 'Success to Fetch All Datas';
    private $messageSuccess = 'Success to Fetch Data';
    private $messageCreate = 'Success to Create Data';
    private $messageUpdate = 'Success to Update Data';

    public function indexDate(Request $request)
    {
        try {
            $tanggal = $request->tanggal;

            $processResult = $this->processIndexDate($tanggal);

            if ($processResult['error']) {
                return $processResult['response'];
            }

            return response()->json($processResult['data'], 200);

        } catch (QueryException $e) {
            return response()->json([
                'message' => $this->messageFail,
                'err' => $e->getTrace()[0],
                'errMsg' => $e->getMessage(),
                'success' => false,
            ], 500);
        }
    }

    public function processIndexDate($tanggal)
    {
        $data = $this->fetchDataMarket($tanggal);

        if ($data['dataMRouters']->isEmpty() || $data['dataLDuty']->isEmpty()) {
            return [
                'error' => true,
                'response' => response()->json(['message' => $this->messageMissing], 401),
            ];
        }

        $formattedDataMRouters = $data['dataMRouters']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
            $bulky = $items->first()['bulky'];
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'item' => $items->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'id_bulky' => $item['id_bulky'],
                        'tanggal' => $item['tanggal'],
                        'nilai' => $item['nilai'],
                        'currency_id' => $item['currency_id'],
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at'],
                    ];
                })
            ];
        })->values();

        $formattedDataLDuty = $data['dataLDuty']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
            $bulky = $items->first()['bulky'];
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'item' => $items->map(function ($item) {
                    return [
                        'id' => $item['id'],
                        'id_bulky' => $item['id_bulky'],
                        'tanggal' => $item['tanggal'],
                        'nilai' => $item['nilai'],
                        'currency_id' => $item['currency_id'],
                        'created_at' => $item['created_at'],
                        'updated_at' => $item['updated_at'],
                    ];
                })
            ];
        })->values();

        $formattedMarketExcludedLevyDuty = $data['marketExcludedLevyDuty']->groupBy('bulky.id')->map(function ($items, $bulkyId) {
            $bulky = $items->first()['bulky'];
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'item' => $items->map(function ($item) {
                    return [
                        'tanggal' => $item['tanggal'],
                        'nilai' => $item['nilai'],
                        'id_bulky' => $item['id_bulky'],
                        'currency_id' => $item['currency_id'],
                    ];
                })
            ];
        })->values();

        return [
            'error' => false,
            'data' => [
                'dataMRouters' => $formattedDataMRouters,
                'averageDataMRoutersPerBulky' => $data['averageDataMRoutersPerBulky'],
                'dataLDuty' => $formattedDataLDuty,
                'averageDataLDutyPerBulky' => $data['averageDataLDutyPerBulky'],
                'currencyRates' => $data['currencyRates'],
                'averageCurrencyRate' => $data['averageCurrencyRate'],
                'marketExcludedLevyDuty' => $formattedMarketExcludedLevyDuty,
                'averageMarketExcludedLevyDutyPerBulky' => $data['averageMarketExcludedLevyDutyPerBulky'],
                'marketValue' => $data['marketValue'],
                'averageMarketValue' => $data['averageMarketValue'],
                'dataCpoKpbn' => $data['dataCpoKpbn'],
                'averageCpoKpbn' => $data['averageCpoKpbn'],
                'message' => $this->messageAll,
            ],
        ];
    }

    public function fetchDataMarket($tanggal)
    {
        $currencies = collect($this->getCurrencies());
        $currencyRates = collect($this->getRateCurrencyData($tanggal, "USD"));

        $dataCpoKpbn = $this->getCpoKpbn($tanggal);
        $dataMRouters = $this->getMarketRouters($tanggal);
        $dataLDuty = $this->getLevyDuty($tanggal);
        $setting = $this->getSetting('pembagi_market_idr');

        $marketExcludedLevyDuty = $this->calculateMarketExcludedLevyDuty($dataMRouters, $dataLDuty, $currencies);
        $averageCurrencyRate = $currencyRates->avg('rate');
        $averageCpoKpbn = $dataCpoKpbn->avg('avg');

        $averageDataMRoutersPerBulky = $this->calculateAveragePerBulky($dataMRouters);
        $averageDataLDutyPerBulky = $this->calculateAveragePerBulky($dataLDuty);
        $averageMarketExcludedLevyDutyPerBulky = $this->calculateAveragePerBulky($marketExcludedLevyDuty);

        $marketValue = $this->calculateMarketValue($marketExcludedLevyDuty, $currencyRates, $setting);
        $averageMarketValue = $this->calculateAverageMarketValue($marketValue);

        return compact(
            'dataMRouters',
            'dataLDuty',
            'dataCpoKpbn',
            'setting',
            'marketExcludedLevyDuty',
            'currencies',
            'currencyRates',
            'averageCurrencyRate',
            'averageDataMRoutersPerBulky',
            'averageDataLDutyPerBulky',
            'averageMarketExcludedLevyDutyPerBulky',
            'averageCpoKpbn',
            'marketValue',
            'averageMarketValue'
        );
    }

    protected function getCpoKpbn($tanggal)
    {
        return cpoKpbn::whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->orderBy('tanggal')
            ->get();
    }

    protected function getMarketRouters($tanggal)
    {
        return MarketRoutersBulky::with('bulky')
            ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->orderBy('tanggal')
            ->get();
    }

    protected function getLevyDuty($tanggal)
    {
        return LevyDutyBulky::with('bulky')
            ->whereYear('tanggal', '=', date('Y', strtotime($tanggal)))
            ->whereMonth('tanggal', '=', date('m', strtotime($tanggal)))
            ->orderBy('tanggal')
            ->get();
    }

    protected function getSetting($name)
    {
        return Setting::where('setting_name', $name)->first();
    }

    protected function calculateMarketExcludedLevyDuty($dataMRouters, $dataLDuty, $currencies)
    {
        return $dataMRouters->map(function ($router) use ($dataLDuty, $currencies) {
            $levyDuty = $dataLDuty->firstWhere('tanggal', $router->tanggal);
            $excludedValue = $router->nilai - ($levyDuty->nilai ?? 0);
            if (empty($router->nilai) || $router->nilai == 0) {
                $excludedValue = 0;
            }

            $currencyDetails = $currencies->firstWhere('id', $router->currency_id);

            return [
                'tanggal' => $router->tanggal,
                'nilai' => $excludedValue,
                'id_bulky' => $router->id_bulky,
                'bulky' => $router->bulky,
                'currency_id' => $router->currency_id,
                'currency' => $currencyDetails,
            ];
        });
    }

    protected function calculateAveragePerBulky($data)
    {
        return $data->groupBy('bulky.id')->map(function ($items) {
            // Handle if bulky is an object or array
            $bulky = $items->first()->bulky ?? $items->first()['bulky'];

            return [
                'id' => $bulky['id'] ?? $bulky->id,
                'name' => $bulky['name'] ?? $bulky->name,
                'average' => $items->avg('nilai'),
            ];
        })->values();
    }


    protected function calculateMarketValue($marketExcludedLevyDuty, $currencyRates, $setting)
    {
        $settingValue = (int) $setting->setting_value;

        return $marketExcludedLevyDuty->groupBy('bulky.id')->map(function ($items) use ($currencyRates, $settingValue) {
            $bulky = $items->first()['bulky'] ?? (object) $items->first()->bulky;

            return [
                'id' => $bulky['id'] ?? $bulky->id,
                'name' => $bulky['name'] ?? $bulky->name,
                'item' => $items->map(function ($item) use ($currencyRates, $settingValue) {
                    $rate = $currencyRates->firstWhere('name', $item['tanggal'])['rate'] ?? 0;
                    $value = ($item['nilai'] * $rate) / $settingValue;
                    return [
                        'tanggal' => $item['tanggal'],
                        'value' => $value,
                    ];
                })
            ];
        })->values();
    }


    protected function calculateAverageMarketValue($marketValue)
    {
        return collect($marketValue)->map(function ($bulky) {
            return [
                'id' => $bulky['id'],
                'name' => $bulky['name'],
                'average' => round(
                    collect($bulky['item'])->avg('value'),
                    2
                ),
            ];
        });
    }

}
