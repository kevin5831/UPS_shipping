<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <style>
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #007bff;
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-size: 16px;
            margin-bottom: 10px; /* space between buttons */
        }

        .btn:hover {
            background-color: #0056b3;
        }

        .button-container {
            display: flex;
            flex-direction: column;
            width: fit-content;
        }
    </style>
</head>
<body>
    <h2>Welcome to UPS Shipping</h2>

    <div class="button-container">
        <a href="{{ route('shipment.form') }}" class="btn">Start Shipment</a>
        <a href="{{ route('shipment.test-auth') }}" class="btn">TestAuth Button</a>
    </div>
</body>
</html>
