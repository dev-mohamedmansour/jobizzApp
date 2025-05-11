<!DOCTYPE html>
<html>
<head>
    <title>Admin Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            text-align: center;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .success {
            color: #28a745;
        }
        .error {
            color: #dc3545;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Admin Email Verification</h1>
    @if (session('success'))
        <p class="success">{{ session('success') }}</p>
        <p><a href="{{ route('admin.login') }}">Proceed to Login</a></p>
    @elseif (session('error'))
        <p class="error">{{ session('error') }}</p>
        <p><a href="{{ route('admin.login') }}">Return to Login</a></p>
    @else
        <p>Verifying your email...</p>
    @endif
</div>
</body>
</html>