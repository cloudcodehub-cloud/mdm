<h2 class="section">Visit</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Client</span><span class="fact-value">{{ $client_name }}</span></td>
        <td><span class="fact-label">Client ID</span><span class="fact-value">{{ $client_number }}</span></td>
        <td><span class="fact-label">Service</span><span class="fact-value">{{ $service }}</span></td>
        <td><span class="fact-label">Visit status</span><span class="fact-value"><span class="badge">{{ $visit_status }}</span></span></td>
    </tr>
    <tr>
        <td><span class="fact-label">DSP</span><span class="fact-value">{{ $dsp_name }}</span></td>
        <td><span class="fact-label">Supervisor</span><span class="fact-value">{{ $supervisor_name ?: '—' }}</span></td>
        <td><span class="fact-label">Scheduled window</span><span class="fact-value">{{ $scheduled_window }}</span></td>
        <td>
            <span class="fact-label">Attendance location</span>
            <span class="fact-value">
                Clock-in: {{ $clock_in_location }}
                @if ($clock_out_location)
                    <br>Clock-out: {{ $clock_out_location }}
                @endif
            </span>
        </td>
    </tr>
</table>

<h2 class="section">Clock history</h2>
<table class="rows">
    <thead>
        <tr>
            <th></th>
            <th>Clock-in</th>
            <th>Clock-out</th>
            <th>Duration</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Original recorded</td>
            <td>{{ $original_clock_in }}</td>
            <td>{{ $original_clock_out ?: '—' }}</td>
            <td>{{ $original_duration ?: '—' }}</td>
        </tr>
        <tr>
            <td>{{ $clocks_adjusted ? 'Corrected / effective' : 'Effective (same as original)' }}</td>
            <td>{{ $effective_clock_in ?: '—' }}</td>
            <td>{{ $effective_clock_out ?: '—' }}</td>
            <td>{{ $effective_duration ?: '—' }}</td>
        </tr>
    </tbody>
</table>
@if ($clocks_adjusted)
    <p class="small muted">Original EVV clock values are preserved. Effective times reflect an approved attendance correction.</p>
@endif

<h2 class="section">Task summary</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Total</span><span class="fact-value">{{ $task_summary['total'] }}</span></td>
        <td><span class="fact-label">Completed</span><span class="fact-value">{{ $task_summary['completed'] }}</span></td>
        <td><span class="fact-label">Skipped</span><span class="fact-value">{{ $task_summary['skipped'] }}</span></td>
        <td><span class="fact-label">Unfinished</span><span class="fact-value">{{ $task_summary['pending'] }}</span></td>
    </tr>
</table>

<h2 class="section">Completed tasks</h2>
@include('documents.partials.task-list', ['tasks' => $completed_tasks, 'mode' => 'completed', 'empty' => 'No completed tasks recorded.'])

<h2 class="section">Skipped tasks</h2>
@include('documents.partials.task-list', ['tasks' => $skipped_tasks, 'mode' => 'skipped', 'empty' => 'No skipped tasks recorded.'])

<h2 class="section">Unfinished / pending tasks</h2>
@include('documents.partials.task-list', ['tasks' => $pending_tasks, 'mode' => 'pending', 'empty' => 'No unfinished tasks.'])

<h2 class="section">Visit notes</h2>
<div class="note">{{ filled($visit_notes) ? $visit_notes : 'No visit notes recorded.' }}</div>

<h2 class="section">Handover note</h2>
<div class="note">{{ filled($handover_note) ? $handover_note : 'No handover note recorded.' }}</div>

<h2 class="section">Exceptions</h2>
@if ($exceptions === [])
    <p class="muted small">No exceptions on this visit.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Type</th>
                <th>Status</th>
                <th>Task</th>
                <th>Detail</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($exceptions as $exception)
                <tr>
                    <td>{{ $exception['type_label'] }}</td>
                    <td><span class="badge">{{ $exception['status_label'] }}</span></td>
                    <td>{{ $exception['task_title'] ?: '—' }}</td>
                    <td>
                        {{ $exception['message'] }}
                        @if (!empty($include_follow_up) && filled($exception['review_notes'] ?? null))
                            <div class="small" style="margin-top:4px;"><strong>Supervisor review:</strong> {{ $exception['review_notes'] }}
                                @if (!empty($exception['reviewed_by_name'])) ({{ $exception['reviewed_by_name'] }}) @endif
                            </div>
                        @endif
                        @if (!empty($include_follow_up) && filled($exception['resolution_notes'] ?? null))
                            <div class="small" style="margin-top:4px;"><strong>Resolution:</strong> {{ $exception['resolution_notes'] }}
                                @if (!empty($exception['resolved_by_name'])) ({{ $exception['resolved_by_name'] }}) @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
