<?php

namespace App\Http\Controllers;

use App\Services\UpsApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            
            try {
                $shipmentResult = $this->upsApiService->createShipment($formData);
                
                Log::info('Shipment created successfully: ' . json_encode($shipmentResult));
                
                echo "<h1>Shipment Created Successfully!</h1>";
                echo "<p><strong>Tracking Number:</strong> " . $shipmentResult['tracking_number'] . "</p>";
                echo "<p><strong>Service:</strong> " . $shipmentResult['service_name'] . "</p>";
                exit(); 
            } catch (\Exception $e) {
                Log::error('UPS API Error in createShipment: ' . $e->getMessage());
                echo "<h1>UPS API Error</h1>";
                echo "<p>" . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
                exit();
            }
        } catch (\Exception $e) {
            Log::error('General error in createShipment: ' . $e->getMessage());
            echo "<h1>General Error</h1>";
            echo "<p>" . $e->getMessage() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
            exit();
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

    public function checkStorage()
    {
        try {
            // Test if storage is properly configured
            $testFile = 'test_' . time() . '.txt';
            $content = 'Storage test: ' . date('Y-m-d H:i:s');
            
            // Try to save to public disk
            Storage::disk('public')->put($testFile, $content);
            
            // Check if file exists
            $exists = Storage::disk('public')->exists($testFile);
            $path = Storage::disk('public')->path($testFile);
            $url = Storage::disk('public')->url($testFile);
            
            // Create labels directory if it doesn't exist
            if (!Storage::disk('public')->exists('labels')) {
                Storage::disk('public')->makeDirectory('labels');
            }
            
            // Try to save to labels directory
            $testLabelFile = 'labels/test_' . time() . '.txt';
            Storage::disk('public')->put($testLabelFile, $content);
            $labelExists = Storage::disk('public')->exists($testLabelFile);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Storage test completed',
                'file_exists' => $exists,
                'file_path' => $path,
                'file_url' => $url,
                'labels_directory_exists' => Storage::disk('public')->exists('labels'),
                'test_label_file_exists' => $labelExists
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Storage test failed',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function testLabelFormats()
    {
        $testData = [
            'selected_service' => '93', // Ground Saver
            'shipper_name' => 'Karl Englund',
            'shipper_address' => '939 Palm Ave',
            'shipper_city' => 'West Hollywood',
            'shipper_state' => 'CA',
            'shipper_postal' => '90069',
            'shipper_country' => 'US',
            'shipper_phone' => '5551234567',
            'recipient_name' => 'Dawn Englund',
            'recipient_address' => '426 Dulton Dr',
            'recipient_city' => 'Toledo',
            'recipient_state' => 'OH',
            'recipient_postal' => '43615',
            'recipient_country' => 'US',
            'recipient_phone' => '5559876543',
            'weight' => '5',
            'length' => '4',
            'width' => '4',
            'height' => '4'
        ];
        
        // Format options to try
        $formats = [
            ["Code" => "GIF", "Description" => "GIF"],
            ["Code" => "ZPL", "Description" => "ZPL"],
            ["Code" => "EPL", "Description" => "EPL"],
            ["Code" => "SPL", "Description" => "SPL"]
        ];
        
        $results = [];
        
        foreach ($formats as $format) {
            try {
                $token = $this->upsApiService->getAccessToken();
                
                $body = [
                    "ShipmentRequest" => [
                        "Request" => [
                            "RequestOption" => "validate",
                            "SubVersion" => "1801",
                            "TransactionReference" => [
                                "CustomerContext" => "Testing label formats"
                            ]
                        ],
                        "Shipment" => [
                            // Shipment details here...
                            "Shipper" => [
                                "Name" => $testData['shipper_name'],
                                "ShipperNumber" => config('ups.account_number'),
                                "Address" => [
                                    "AddressLine" => $testData['shipper_address'],
                                    "City" => $testData['shipper_city'],
                                    "StateProvinceCode" => $testData['shipper_state'],
                                    "PostalCode" => $testData['shipper_postal'],
                                    "CountryCode" => $testData['shipper_country']
                                ]
                            ],
                            "ShipTo" => [
                                "Name" => $testData['recipient_name'],
                                "Address" => [
                                    "AddressLine" => $testData['recipient_address'],
                                    "City" => $testData['recipient_city'],
                                    "StateProvinceCode" => $testData['recipient_state'],
                                    "PostalCode" => $testData['recipient_postal'],
                                    "CountryCode" => $testData['recipient_country']
                                ]
                            ],
                            "ShipFrom" => [
                                "Name" => $testData['shipper_name'],
                                "Address" => [
                                    "AddressLine" => $testData['shipper_address'],
                                    "City" => $testData['shipper_city'],
                                    "StateProvinceCode" => $testData['shipper_state'],
                                    "PostalCode" => $testData['shipper_postal'],
                                    "CountryCode" => $testData['shipper_country']
                                ]
                            ],
                            "Service" => [
                                "Code" => $testData['selected_service']
                            ],
                            "Package" => [
                                [
                                    "Packaging" => [
                                        "Code" => "02"
                                    ],
                                    "PackageWeight" => [
                                        "UnitOfMeasurement" => [
                                            "Code" => "LBS"
                                        ],
                                        "Weight" => $testData['weight']
                                    ]
                                ]
                            ],
                            "PaymentInformation" => [
                                "ShipmentCharge" => [
                                    "Type" => "01",
                                    "BillShipper" => [
                                        "AccountNumber" => config('ups.account_number')
                                    ]
                                ]
                            ]
                        ],
                        "LabelSpecification" => [
                            "LabelImageFormat" => $format,
                            "HTTPUserAgent" => "Mozilla/5.0"
                        ]
                    ]
                ];
                
                $baseUrl = config('ups.sandbox') ? config('ups.sandbox_url') : config('ups.production_url');
                $hostHeader = config('ups.sandbox') ? 'wwwcie.ups.com' : 'onlinetools.ups.com';
                
                $response = \Illuminate\Support\Facades\Http::withToken($token)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'transId' => uniqid(),
                        'transactionSrc' => 'testing',
                        'Host' => $hostHeader,
                    ])
                    ->post($baseUrl . '/api/shipments/v1/ship', $body);
                
                if ($response->successful()) {
                    $results[$format['Code']] = [
                        'status' => 'success',
                        'message' => 'Label generated successfully'
                    ];
                } else {
                    $results[$format['Code']] = [
                        'status' => 'error',
                        'message' => $response->body()
                    ];
                }
            } catch (\Exception $e) {
                $results[$format['Code']] = [
                    'status' => 'exception',
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return response()->json([
            'results' => $results,
            'recommendation' => 'Try updating your UpsApiService.php file to use the format that succeeded'
        ]);
    }
}