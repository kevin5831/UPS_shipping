@extends('layouts.app')

@section('content')
<div class="container">
    <h2>UPS Shipping Rates</h2>

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if (isset($rates) && count($rates))
        <div class="card mb-4">
            <div class="card-header">Available Shipping Services</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Delivery Time</th>
                                <th>Cost</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rates as $rate)
                                <tr>
                                    <td>{{ $rate['service'] }}</td>
                                    <td>
                                        @if(isset($rate['delivery_days']))
                                            {{ $rate['delivery_days'] }} business day(s)
                                        @else
                                            Varies
                                        @endif
                                    </td>
                                    <td>${{ number_format($rate['total_charges'], 2) }} {{ $rate['currency'] }}</td>
                                    <td>
                                        <form action="{{ route('shipment.create') }}" method="POST">
                                            @csrf
                                            <!-- Pass all the form data as hidden fields -->
                                            @foreach ($formData as $key => $value)
                                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                            @endforeach
                                            <input type="hidden" name="selected_service" value="{{ $rate['service_code'] }}">
                                            <button type="submit" class="btn btn-sm btn-success">Select</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Shipment Details</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h5>From:</h5>
                        <p>
                            {{ $formData['shipper_name'] }}<br>
                            {{ $formData['shipper_address'] }}<br>
                            {{ $formData['shipper_city'] }}, {{ $formData['shipper_state'] }} {{ $formData['shipper_postal'] }}<br>
                            {{ $formData['shipper_country'] }}
                        </p>
                    </div>
                    <div class="col-md-6">
                        <h5>To:</h5>
                        <p>
                            {{ $formData['recipient_name'] }}<br>
                            {{ $formData['recipient_address'] }}<br>
                            {{ $formData['recipient_city'] }}, {{ $formData['recipient_state'] }} {{ $formData['recipient_postal'] }}<br>
                            {{ $formData['recipient_country'] }}
                        </p>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <h5>Package Details:</h5>
                        <p>
                            Weight: {{ $formData['weight'] }} lbs<br>
                            Dimensions: {{ $formData['length'] }} x {{ $formData['width'] }} x {{ $formData['height'] }} inches
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="alert alert-warning">
            No shipping rates available. Please check your shipment details and try again.
        </div>
        
        <a href="{{ route('shipment.form') }}" class="btn btn-primary">Back to Shipment Form</a>
    @endif
</div>
@endsection