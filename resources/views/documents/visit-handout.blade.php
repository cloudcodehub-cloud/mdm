@php
    /** @var list<array{title: string, instructions: ?string, is_required: bool, is_critical: bool, source_label: string}> $tasks */
@endphp
<h2 class="section">Visit</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Client</span><span class="fact-value">{{ $client_name }}</span></td>
        <td><span class="fact-label">Client ID</span><span class="fact-value">{{ $client_number }}</span></td>
        <td><span class="fact-label">Service</span><span class="fact-value">{{ $service }}</span></td>
        <td><span class="fact-label">Visit date</span><span class="fact-value">{{ $visit_date }}</span></td>
    </tr>
    <tr>
        <td><span class="fact-label">Scheduled window</span><span class="fact-value">{{ $scheduled_window }}</span></td>
        <td><span class="fact-label">Assigned DSP</span><span class="fact-value">{{ $dsp_name }}</span></td>
        <td><span class="fact-label">Supervisor</span><span class="fact-value">{{ $supervisor_name ?: '—' }}</span></td>
        <td>
            <span class="fact-label">Programs</span>
            <span class="fact-value">{{ $services === [] ? '—' : implode(', ', $services) }}</span>
        </td>
    </tr>
</table>

<h2 class="section">Visit instructions</h2>
@if (filled($instructions))
    <div class="note">{{ $instructions }}</div>
@else
    <p class="muted small">No additional visit instructions recorded.</p>
@endif

<h2 class="section">Tasks for this visit</h2>
@if ($tasks === [])
    <p class="muted small">No care-plan or visit-only tasks are included for this visit.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Task</th>
                <th>Indicators</th>
                <th>Source</th>
                <th>Instructions</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($tasks as $task)
                <tr>
                    <td>{{ $task['title'] }}</td>
                    <td>
                        @if ($task['is_required'])
                            <span class="badge">Required</span>
                        @endif
                        @if ($task['is_critical'])
                            <span class="badge">Critical</span>
                        @endif
                        @if (! $task['is_required'] && ! $task['is_critical'])
                            <span class="muted">—</span>
                        @endif
                    </td>
                    <td>{{ $task['source_label'] }}</td>
                    <td>{{ $task['instructions'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2 class="section">Visit notes (handwritten)</h2>
<p class="muted small">Use this space during the visit. These notes are not stored until entered in MDM.</p>
<div class="blank-notes"></div>

<table class="ack">
    <tr>
        <td>
            <div class="line">DSP initials</div>
        </td>
        <td>
            <div class="line">Time / date</div>
        </td>
    </tr>
</table>
