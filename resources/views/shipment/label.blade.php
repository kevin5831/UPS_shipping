@extends('layouts.app')

@section('content')
<div class="container">
    <div class="card">
        <div class="card-header">
            <h2>Shipment Created Successfully</h2>
        </div>
        <div class="card-body text-center">
            <div class="alert alert-success">
                <h4>Your shipment has been created!</h4>
                <p>Tracking Number: <strong>{{ $trackingNumber }}</strong></p>
                <p>Service: <strong>{{ $serviceName }}</strong></p>
            </div>

            <div class="mt-4">
                <h3>Shipping Label</h3>
                <div class="mt-3">
                    <img src="{{ $labelUrl }}" alt="Shipping Label" class="img-fluid border" style="max-width: 600px;">
                </div>
                
                <div class="mt-4">
                    <a href="{{ $labelUrl }}" download class="btn btn-primary">
                        <i class="fa fa-download"></i> Download Label
                    </a>
                    <a href="{{ route('shipment.form') }}" class="btn btn-secondary ms-2">
                        Create Another Shipment
                    </a>
                </div>
            </div>
            
            <div class="mt-5">
                <div class="alert alert-info">
                    <p><strong>Next Steps:</strong></p>
                    <ol class="text-start">
                        <li>Print this label and affix it to your package</li>
                        <li>Drop off your package at any UPS location or schedule a pickup</li>
                        <li>Track your shipment using the tracking number above</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection