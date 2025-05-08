<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class UpsApiService
{
    protected $baseUrl;
    protected $accountNumber;
    protected $hostHeader;

    public function __construct()
    {
        $this->baseUrl = config('ups.sandbox') ? config('ups.sandbox_url') : config('ups.production_url');
        $this->accountNumber = config('ups.account_number');
        
        // Set the appropriate host header based on environment
        $this->hostHeader = config('ups.sandbox') ? 'wwwcie.ups.com' : 'onlinetools.ups.com';
    }

    /**
     * Get UPS OAuth access token
     */
    public function getAccessToken()
    {
        $clientId = config('ups.client_id');
        $clientSecret = config('ups.client_secret');
        $credentials = base64_encode("$clientId:$clientSecret");
    
        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Host' => $this->hostHeader, // Add Host header
        ])->asForm()->post($this->baseUrl . '/security/v1/oauth/token', [
            'grant_type' => 'client_credentials',
        ]);

        if ($response->successful()) {
            $data = $response->json();
            return $data['access_token'];
        }

        Log::error('UPS API Error: ' . $response->body());
        throw new \Exception('Failed to get access token: ' . $response->body());
    }

    /**
     * Get shipping rates for all available services using Shop
     */
    public function getRates(array $data)
    {
        $token = $this->getAccessToken();

        $body = [
            "RateRequest" => [
                "Request" => [
                    "RequestOption" => "Shop", // "Shop" returns all available services
                    "TransactionReference" => [
                        "CustomerContext" => "Rating and service selection"
                    ]
                ],
                "Shipment" => [
                    "Shipper" => [
                        "Name" => $data['shipper_name'],
                        "ShipperNumber" => $this->accountNumber,
                        "Address" => [
                            "AddressLine" => $data['shipper_address'],
                            "City" => $data['shipper_city'],
                            "StateProvinceCode" => $data['shipper_state'],
                            "PostalCode" => $data['shipper_postal'],
                            "CountryCode" => $data['shipper_country']
                        ]
                    ],
                    "ShipTo" => [
                        "Name" => $data['recipient_name'],
                        "Address" => [
                            "AddressLine" => $data['recipient_address'],
                            "City" => $data['recipient_city'],
                            "StateProvinceCode" => $data['recipient_state'],
                            "PostalCode" => $data['recipient_postal'],
                            "CountryCode" => $data['recipient_country'],
                            // Removed ResidentialAddressIndicator to avoid residential surcharge
                        ]
                    ],
                    "ShipFrom" => [
                        "Name" => $data['shipper_name'],
                        "Address" => [
                            "AddressLine" => $data['shipper_address'],
                            "City" => $data['shipper_city'],
                            "StateProvinceCode" => $data['shipper_state'],
                            "PostalCode" => $data['shipper_postal'],
                            "CountryCode" => $data['shipper_country']
                        ]
                    ],
                    "Package" => [
                        [
                            "PackagingType" => [
                                "Code" => "02", // Customer Supplied Package
                                "Description" => "Package"
                            ],
                            "Dimensions" => [
                                "UnitOfMeasurement" => [
                                    "Code" => "IN",
                                    "Description" => "Inches"
                                ],
                                "Length" => $data['length'] ?? "4",
                                "Width" => $data['width'] ?? "4",
                                "Height" => $data['height'] ?? "4"
                            ],
                            "PackageWeight" => [
                                "UnitOfMeasurement" => [
                                    "Code" => "LBS",
                                    "Description" => "Pounds"
                                ],
                                "Weight" => $data['weight'] ?? "5"
                            ]
                        ]
                    ],
                    "ShipmentRatingOptions" => [
                        "NegotiatedRatesIndicator" => "true"
                    ],
                    // Add PaymentInformation to specify the billing account
                    "PaymentInformation" => [
                        "ShipmentCharge" => [
                            "Type" => "01", // Transportation charges
                            "BillShipper" => [
                                "AccountNumber" => $this->accountNumber
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'transId' => uniqid(),
                'transactionSrc' => 'testing',
                'Host' => $this->hostHeader, // Add Host header
            ])
            ->post($this->baseUrl . '/api/rating/v1/shop', $body);

        if (!$response->successful()) {
            Log::error('UPS Rate API Error: ' . $response->body());
            throw new \Exception('Rate API Error: ' . $response->body());
        }

        $results = $response->json();
        $rates = [];

        // Log the entire response for debugging
        Log::debug('UPS Rate Response: ' . json_encode($results, JSON_PRETTY_PRINT));

        if (isset($results['RateResponse']['RatedShipment'])) {
            foreach ($results['RateResponse']['RatedShipment'] as $rate) {
                $serviceCode = $rate['Service']['Code'];
                $serviceName = $this->getServiceName($serviceCode);
                
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
                
                $rates[] = [
                    'service_code' => $serviceCode,
                    'service' => $serviceName,
                    'total_charges' => $totalCharges,
                    'currency' => $currency,
                    'delivery_days' => $rate['GuaranteedDelivery']['BusinessDaysInTransit'] ?? null,
                ];
            }
        }

        return $rates;
    }

    /**
     * Get Ground Saver Rates (codes 92 for under 1lb and 93 for 1lb+)
     */
    public function getGroundSaverRate(array $data)
    {
        $token = $this->getAccessToken();
        
        // Determine which Ground Saver code to use based on weight
        $weight = floatval($data['weight'] ?? "5");
        $serviceCode = ($weight < 1) ? "92" : "93";

        $body = [
            "RateRequest" => [
                "Request" => [
                    "RequestOption" => "Rate", // Single rate request
                    "TransactionReference" => [
                        "CustomerContext" => "UPS Ground Saver Rate"
                    ]
                ],
                "Shipment" => [
                    "Shipper" => [
                        "Name" => $data['shipper_name'],
                        "ShipperNumber" => $this->accountNumber,
                        "Address" => [
                            "AddressLine" => $data['shipper_address'],
                            "City" => $data['shipper_city'],
                            "StateProvinceCode" => $data['shipper_state'],
                            "PostalCode" => $data['shipper_postal'],
                            "CountryCode" => $data['shipper_country']
                        ]
                    ],
                    "ShipTo" => [
                        "Name" => $data['recipient_name'],
                        "Address" => [
                            "AddressLine" => $data['recipient_address'],
                            "City" => $data['recipient_city'],
                            "StateProvinceCode" => $data['recipient_state'],
                            "PostalCode" => $data['recipient_postal'],
                            "CountryCode" => $data['recipient_country']
                            // No residential indicator to avoid surcharge
                        ]
                    ],
                    "ShipFrom" => [
                        "Name" => $data['shipper_name'],
                        "Address" => [
                            "AddressLine" => $data['shipper_address'],
                            "City" => $data['shipper_city'],
                            "StateProvinceCode" => $data['shipper_state'],
                            "PostalCode" => $data['shipper_postal'],
                            "CountryCode" => $data['shipper_country']
                        ]
                    ],
                    "Service" => [
                        "Code" => $serviceCode,
                        "Description" => ($serviceCode == "93") ? "UPS Ground Saver" : "UPS Ground Saver (Under 1lb)"
                    ],
                    "Package" => [
                        [
                            "PackagingType" => [
                                "Code" => "02" // Customer Supplied Package
                            ],
                            "Dimensions" => [
                                "UnitOfMeasurement" => [
                                    "Code" => "IN"
                                ],
                                "Length" => $data['length'] ?? "4",
                                "Width" => $data['width'] ?? "4",
                                "Height" => $data['height'] ?? "4"
                            ],
                            "PackageWeight" => [
                                "UnitOfMeasurement" => [
                                    "Code" => "LBS"
                                ],
                                "Weight" => $data['weight'] ?? "5"
                            ]
                        ]
                    ],
                    "ShipmentRatingOptions" => [
                        "NegotiatedRatesIndicator" => "true"
                    ],
                    "PaymentInformation" => [
                        "ShipmentCharge" => [
                            "Type" => "01", // Transportation charges
                            "BillShipper" => [
                                "AccountNumber" => $this->accountNumber
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::withToken($token)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'transId' => uniqid(),
                'transactionSrc' => 'testing',
                'Host' => $this->hostHeader, // Add Host header
            ])
            ->post($this->baseUrl . '/api/rating/v2409/Rate', $body);

        // Log the response for debugging
        Log::debug('UPS Ground Saver API Response: ' . $response->body());

        if (!$response->successful()) {
            // Ground Saver might not be available, so we don't want to throw an error
            // Instead, just return null
            Log::info('UPS Ground Saver not available: ' . $response->body());
            return null;
        }

        return $response->json();
    }

    /**
     * Create a shipment and generate label
     */
    public function createShipment(array $data)
    {
        try {
            $token = $this->getAccessToken();

            $serviceCode = $data['selected_service'] ?? '03'; // Default to UPS Ground if not specified
            
            // Debug logging
            Log::info('Creating shipment with service code: ' . $serviceCode);

            $body = [
                "ShipmentRequest" => [
                    "Request" => [
                        "RequestOption" => "validate",
                        "SubVersion" => "1801",
                        "TransactionReference" => [
                            "CustomerContext" => "Creating Shipment and Label"
                        ]
                    ],
                    "Shipment" => [
                        "Description" => "Package from " . $data['shipper_name'],
                        "Shipper" => [
                            "Name" => $data['shipper_name'],
                            "AttentionName" => $data['shipper_name'],
                            "ShipperNumber" => $this->accountNumber,
                            "Phone" => [
                                "Number" => $data['shipper_phone'] ?? "5551234567"
                            ],
                            "Address" => [
                                "AddressLine" => $data['shipper_address'],
                                "City" => $data['shipper_city'],
                                "StateProvinceCode" => $data['shipper_state'],
                                "PostalCode" => $data['shipper_postal'],
                                "CountryCode" => $data['shipper_country']
                            ]
                        ],
                        "ShipTo" => [
                            "Name" => $data['recipient_name'],
                            "AttentionName" => $data['recipient_name'],
                            "Phone" => [
                                "Number" => $data['recipient_phone'] ?? "5559876543"
                            ],
                            "Address" => [
                                "AddressLine" => $data['recipient_address'],
                                "City" => $data['recipient_city'],
                                "StateProvinceCode" => $data['recipient_state'],
                                "PostalCode" => $data['recipient_postal'],
                                "CountryCode" => $data['recipient_country']
                                // Removed ResidentialAddressIndicator to avoid residential surcharge
                            ]
                        ],
                        "ShipFrom" => [
                            "Name" => $data['shipper_name'],
                            "AttentionName" => $data['shipper_name'],
                            "Phone" => [
                                "Number" => $data['shipper_phone'] ?? "5551234567"
                            ],
                            "Address" => [
                                "AddressLine" => $data['shipper_address'],
                                "City" => $data['shipper_city'],
                                "StateProvinceCode" => $data['shipper_state'],
                                "PostalCode" => $data['shipper_postal'],
                                "CountryCode" => $data['shipper_country']
                            ]
                        ],
                        "PaymentInformation" => [
                            "ShipmentCharge" => [
                                "Type" => "01", // Transportation charges
                                "BillShipper" => [
                                    "AccountNumber" => $this->accountNumber
                                ]
                            ]
                        ],
                        "Service" => [
                            "Code" => $serviceCode,
                            "Description" => $this->getServiceName($serviceCode)
                        ],
                        "Package" => [
                            [
                                "Description" => "Package",
                                "Packaging" => [
                                    "Code" => "02", // Customer Supplied Package
                                    "Description" => "Package"
                                ],
                                "Dimensions" => [
                                    "UnitOfMeasurement" => [
                                        "Code" => "IN",
                                        "Description" => "Inches"
                                    ],
                                    "Length" => $data['length'] ?? "4",
                                    "Width" => $data['width'] ?? "4",
                                    "Height" => $data['height'] ?? "4"
                                ],
                                "PackageWeight" => [
                                    "UnitOfMeasurement" => [
                                        "Code" => "LBS",
                                        "Description" => "Pounds"
                                    ],
                                    "Weight" => $data['weight'] ?? "5"
                                ],
                                "PackageServiceOptions" => []
                            ]
                        ],
                        // Add rating options to get negotiated rates
                        "ShipmentRatingOptions" => [
                            "NegotiatedRatesIndicator" => "true"
                        ],
                        "ShipmentServiceOptions" => []
                    ],
                    "LabelSpecification" => [
                        "LabelImageFormat" => [
                            "Code" => "GIF",
                            "Description" => "GIF"
                        ],
                        "HTTPUserAgent" => "Mozilla/5.0"
                    ]
                ]
            ];

            $response = Http::withToken($token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'transId' => uniqid(),
                    'transactionSrc' => 'testing',
                    'Host' => $this->hostHeader, // Add Host header
                ])
                ->post($this->baseUrl . '/api/shipments/v1/ship', $body);

            if (!$response->successful()) {
                Log::error('UPS Ship API Error: ' . $response->body());
                throw new \Exception('Shipment creation failed: ' . $response->body());
            }

            $results = $response->json();
            
            $label = $results['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
            $trackingNumber = $results['ShipmentResponse']['ShipmentResults']['PackageResults']['TrackingNumber'];
            
            $filename = 'label_' . $trackingNumber . '.gif';
            $relativePath = 'labels/' . $filename;
            
            Storage::disk('public')->makeDirectory('labels', 0755, true, true);
            Storage::disk('public')->put($relativePath, base64_decode($label));

            return [
                'label_url' => asset('storage/' . $relativePath),
                'tracking_number' => $trackingNumber,
                'service_code' => $serviceCode,
                'service_name' => $this->getServiceName($serviceCode)
            ];
        } catch (\Exception $e) {
            Log::error('UPS Shipment Creation Exception: ' . $e->getMessage());
            Log::error('UPS Shipment Creation Exception Trace: ' . $e->getTraceAsString());
            throw $e; // Re-throw to be caught by the controller
        }
    }

    /**
     * Helper method to get service name from code
     */
    private function getServiceName($code)
    {
        $services = [
            '01' => 'UPS Next Day Air',
            '02' => 'UPS 2nd Day Air',
            '03' => 'UPS Ground',
            '07' => 'UPS Worldwide Express',
            '08' => 'UPS Worldwide Expedited', 
            '11' => 'UPS Standard',
            '12' => 'UPS 3 Day Select',
            '13' => 'UPS Next Day Air Saver',
            '14' => 'UPS Next Day Air Early',
            '54' => 'UPS Worldwide Express Plus',
            '59' => 'UPS 2nd Day Air A.M.',
            '65' => 'UPS Saver',
            '70' => 'UPS Access Point Economy',
            '92' => 'UPS Ground Saver (Under 1lb)',
            '93' => 'UPS Ground Saver',
        ];

        return $services[$code] ?? "UPS Service Code $code";
    }
}