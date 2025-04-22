<!DOCTYPE html>
<html>
<head>
    <title>Your Sub-admin Credentials</title>
</head>
<body>
<h1>Welcome, {{ $name }}!</h1>
<p>You have been added as a {{ $role }} sub-admin.</p>
<p>Your login credentials are:</p>
<p>Email: {{ $email }}</p>
<p>Password: {{ $password }}</p>
<p>Please change your password after your first login.</p>
</body>
</html>