<h2 class="section">Report scope</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Employee</span><span class="fact-value">{{ $employee_name }}</span></td>
        <td><span class="fact-label">Employee number</span><span class="fact-value">{{ $employee_number ?: '—' }}</span></td>
        <td><span class="fact-label">Supervisor</span><span class="fact-value">{{ $supervisor_name ?: '—' }}</span></td>
        <td><span class="fact-label">Date range</span><span class="fact-value">{{ $date_range }}</span></td>
    </tr>
    <tr>
        <td><span class="fact-label">Rows</span><span class="fact-value">{{ $row_count }}</span></td>
        <td><span class="fact-label">Scheduled hours</span><span class="fact-value">{{ $total_scheduled_hours }}</span></td>
        <td><span class="fact-label">Actual / effective hours</span><span class="fact-value">{{ $total_actual_hours }}</span></td>
        <td><span class="fact-label">Kind</span><span class="fact-value">Hours and attendance only</span></td>
    </tr>
</table>

<h2 class="section">Attendance rows</h2>
@if ($rows === [])
    <p class="muted small">No attendance rows in this reporting scope.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Client</th>
                <th>Scheduled</th>
                <th>Actual / effective</th>
                <th>Status</th>
                <th>Indicators</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['service_date_label'] }}</td>
                    <td>
                        {{ $row['employee_name'] }}
                        <div class="small muted">{{ $row['employee_number'] }}</div>
                    </td>
                    <td>
                        {{ $row['client_name'] }}
                        <div class="small muted">{{ $row['client_number'] }}</div>
                    </td>
                    <td>{{ $row['scheduled_hours'] }}</td>
                    <td>{{ $row['actual_hours'] }}</td>
                    <td><span class="badge">{{ $row['status_label'] }}</span></td>
                    <td>
                        @if ($row['corrected']) <span class="badge">Corrected</span> @endif
                        @if ($row['has_exception']) <span class="badge">Exception</span> @endif
                        @if (! $row['corrected'] && ! $row['has_exception']) — @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<p class="small muted" style="margin-top:12px;">This document reports hours and attendance only. It does not include pay rates, taxes, or payroll calculations.</p>
