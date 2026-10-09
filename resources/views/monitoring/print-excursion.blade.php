<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Excursion report</title>
    <style>body{font-family:Georgia,serif;margin:2rem}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ccc;padding:.4rem;text-align:left}</style>
</head>
<body>
    <h1>Temperature excursion report</h1>
    <p>Generated {{ now() }}</p>
    <table>
        <thead><tr><th>When</th><th>Chamber</th><th>Temp</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>
            @foreach ($alarms as $alarm)
                <tr>
                    <td>{{ $alarm->created_at }}</td>
                    <td>{{ $alarm->chamber?->code }}</td>
                    <td>{{ $alarm->reading_temperature }}</td>
                    <td>{{ $alarm->status }}</td>
                    <td>{{ $alarm->message }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
