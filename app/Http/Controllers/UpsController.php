<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\UpsApiService;
use Illuminate\Support\Facades\Log;

class UpsController extends Controller
{
    protected $upsApiService;

    public function __construct(UpsApiService $upsApiService)
    {
        $this->upsApiService = $upsApiService;
    }

    /**
     * Show the shipment form
     */
    public function showForm()
    {
        return view('shipment.form');
    }

    /**
     * Get shipping rates
     */
    public function getRate(Request $request)
    {
        try {
            // Get regular rates via Shop
            $shopRates = $this->upsApiService->getRates($request->all());
            
            // Try to get Ground Saver rates (codes 92 and 93)
            $groundSaverResult = $this->upsApiService->getGroundSaverRate($request->all());
            $groundSaverRates = [];
            
            if ($groundSaverResult && isset($groundSaverResult['RateResponse']['RatedShipment'])) {
                foreach ($groundSaverResult['RateResponse']['RatedShipment'] as $rate) {
                    $serviceCode = $rate['Service']['Code'];
                    $serviceName = $serviceCode == '93' ? 'UPS Ground Saver' : 'UPS Ground Saver (Under 1lb)';
                    
                    // Check for negotiated rates first
                    $totalCharges = null;
                    $currency = null;
                    
                    // Use negotiated rates if available
                    if (isset($rate['NegotiatedRateCharges']['TotalCharge'])) {
                        $totalCharges = $rate['NegotiatedRateCharges']['TotalCharge']['MonetaryValue'];
                        $currency = $rate['NegotiatedRateCharges']['TotalCharge']['CurrencyCode'];
                    } else {
                        $totalCharges = $rate['TotalCharges']['MonetaryValue'];
                        $currency = $rate['TotalCharges']['CurrencyCode'];
                    }
                    
                    $groundSaverRates[] = [
                        'service_code' => $serviceCode,
                        'service' => $serviceName,
                        'total_charges' => $totalCharges,
                        'currency' => $currency,
                        'delivery_days' => $rate['GuaranteedDelivery']['BusinessDaysInTransit'] ?? null,
                    ];
                }
            }
            
            // Combine all rates
            $rates = array_merge($shopRates, $groundSaverRates);
            
            // Sort by price
            usort($rates, function($a, $b) {
                return $a['total_charges'] <=> $b['total_charges'];
            });
            
            return view('shipment.rate', [
                'rates' => $rates,
                'formData' => $request->all()
            ]);
        } catch (\Exception $e) {
            Log::error('UPS Rate Error: ' . $e->getMessage());
            return back()->with('error', 'Error getting rates: ' . $e->getMessage());
        }
    }

    /**
     * Create shipment and generate label
     */
    public function createShipment(Request $request)
    {
        try {
            $formData = $request->all();
            
            $shipmentResult = $this->upsApiService->createShipment($formData);
            
            return view('shipment.label', [
                'labelUrl' => $shipmentResult['label_url'],
                'trackingNumber' => $shipmentResult['tracking_number'],
                'serviceName' => $shipmentResult['service_name']
            ]);
        } catch (\Exception $e) {
            Log::error('UPS Shipment Error: ' . $e->getMessage());
            return back()->with('error', 'Shipment creation failed: ' . $e->getMessage());
        }
    }

    /**
     * Verify if an access token can be generated (for testing)
     */
    public function testAuthentication()
    {
        try {
            $accessToken = $this->upsApiService->getAccessToken();
            return response()->json(['token' => $accessToken]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}