@extends('layouts.app')

@section('content')
<div class="container">
    <h2>UPS Shipment Form</h2>

    <form action="{{ route('shipment.rate') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>From ZIP:</label>
            <input type="text" name="from_zip" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>To ZIP:</label>
            <input type="text" name="to_zip" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Weight (lbs):</label>
            <input type="number" name="weight" step="0.1" class="form-control" required>
        </div>

        <button type="submit" class="btn btn-primary">Get Rate</button>
    </form>

    @isset($rate['RateResponse'])
        <h4>Estimated Rate: ${{ $rate['RateResponse']['RatedShipment'][0]['TotalCharges']['MonetaryValue'] }}</h4>

        <form action="{{ route('shipment.create') }}" method="POST">
            @csrf
            @foreach ($formData as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <button type="submit">Create Shipment & Download Label</button>
        </form>
    @endisset
</div>
@endsection
