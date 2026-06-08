<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Report</title>
    <style>body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; }</style>
</head>
<body>
    <h1>Financial Report</h1>
    <pre>{{ json_encode($payload, JSON_PRETTY_PRINT) }}</pre>
</body>
</html>
