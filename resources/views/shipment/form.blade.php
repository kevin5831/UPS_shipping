@extends('layouts.app')

@section('content')
<div class="container">
    <h2>UPS Shipment Form</h2>

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('shipment.rate') }}" method="POST">
        @csrf
        <!-- Shipper Information -->
        <div class="card mb-4">
            <div class="card-header">Shipper Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Name:</label>
                            <input type="text" name="shipper_name" class="form-control" value="Karl Englund" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Phone:</label>
                            <input type="text" name="shipper_phone" class="form-control" value="555-123-4567" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Address Line:</label>
                    <input type="text" name="shipper_address" class="form-control" value="939 Palm Ave" required>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label>City:</label>
                            <input type="text" name="shipper_city" class="form-control" value="West Hollywood" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label>State:</label>
                            <input type="text" name="shipper_state" class="form-control" value="CA" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label>ZIP Code:</label>
                            <input type="text" name="shipper_postal" class="form-control" value="90069" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Country:</label>
                    <input type="text" name="shipper_country" class="form-control" value="US" required>
                </div>
            </div>
        </div>

        <!-- Recipient Information -->
        <div class="card mb-4">
            <div class="card-header">Recipient Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Name:</label>
                            <input type="text" name="recipient_name" class="form-control" value="Dawn Englund" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label>Phone:</label>
                            <input type="text" name="recipient_phone" class="form-control" value="555-987-6543" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Address Line:</label>
                    <input type="text" name="recipient_address" class="form-control" value="426 Dulton Dr" required>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label>City:</label>
                            <input type="text" name="recipient_city" class="form-control" value="Toledo" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label>State:</label>
                            <input type="text" name="recipient_state" class="form-control" value="OH" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label>ZIP Code:</label>
                            <input type="text" name="recipient_postal" class="form-control" value="43615" required>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label>Country:</label>
                    <input type="text" name="recipient_country" class="form-control" value="US" required>
                </div>
            </div>
        </div>

        <!-- Package Information -->
        <div class="card mb-4">
            <div class="card-header">Package Information</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label>Weight (lbs):</label>
                            <input type="number" name="weight" step="0.1" class="form-control" value="5" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label>Length (inches):</label>
                            <input type="number" name="length" step="0.1" class="form-control" value="4" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label>Width (inches):</label>
                            <input type="number" name="width" step="0.1" class="form-control" value="4" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3">
                            <label>Height (inches):</label>
                            <input type="number" name="height" step="0.1" class="form-control" value="4" required>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Get Shipping Rates</button>
    </form>
</div>
@endsection