{{-- Reporter-layer Incident content shared by the submitted and final documents. --}}
<h2 class="section">Incident</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Tracking number</span><span class="fact-value">{{ $tracking_number ?: '—' }}</span></td>
        <td><span class="fact-label">Status</span><span class="fact-value"><span class="badge">{{ $status_label }}</span></span></td>
        <td><span class="fact-label">Client</span><span class="fact-value">{{ $client_name }}</span></td>
        <td><span class="fact-label">Client ID</span><span class="fact-value">{{ $client_number }}</span></td>
    </tr>
    <tr>
        <td><span class="fact-label">Reporter</span><span class="fact-value">{{ $reporter_name }}</span></td>
        <td><span class="fact-label">Occurred</span><span class="fact-value">{{ $occurred_at }}</span></td>
        <td><span class="fact-label">Original category</span><span class="fact-value">{{ $original_category ?: '—' }}</span></td>
        <td><span class="fact-label">Original subcategory</span><span class="fact-value">{{ $original_subcategory ?: '—' }}</span></td>
    </tr>
</table>

<h2 class="section">Summary</h2>
<div class="note">{{ filled($summary) ? $summary : 'No summary recorded.' }}</div>

<h2 class="section">Immediate action taken</h2>
<div class="note">{{ filled($immediate_action) ? $immediate_action : 'No immediate action recorded.' }}</div>

<h2 class="section">Injury</h2>
@if ($injured)
    <table class="facts">
        <tr>
            <td><span class="fact-label">Injury type</span><span class="fact-value">{{ $injury_type ?: '—' }}</span></td>
            <td><span class="fact-label">Body location</span><span class="fact-value">{{ $injury_body_location ?: '—' }}</span></td>
            <td><span class="fact-label">Medical check</span><span class="fact-value">{{ $injury_medical_check ?: '—' }}</span></td>
            <td><span class="fact-label">Injury occurred</span><span class="fact-value">{{ $injury_occurred_at ?: '—' }}</span></td>
        </tr>
    </table>
    @if (filled($injury_notes))
        <div class="note" style="margin-top:4px;">{{ $injury_notes }}</div>
    @endif
@else
    <p class="muted small">No injury reported.</p>
@endif

<h2 class="section">Intervention</h2>
@if ($intervention)
    <table class="facts">
        <tr>
            <td><span class="fact-label">Type</span><span class="fact-value">{{ $intervention_type ?: '—' }}</span></td>
            <td><span class="fact-label">Started</span><span class="fact-value">{{ $intervention_started_at ?: '—' }}</span></td>
            <td><span class="fact-label">Stopped</span><span class="fact-value">{{ $intervention_stopped_at ?: '—' }}</span></td>
            <td><span class="fact-label">Supervisor notified</span><span class="fact-value">{{ $supervisor_notified ? 'Yes' : 'No' }}</span></td>
        </tr>
    </table>
    @if (filled($intervention_notes))
        <div class="note" style="margin-top:4px;">{{ $intervention_notes }}</div>
    @endif
@else
    <p class="muted small">No intervention reported. Supervisor notified: {{ $supervisor_notified ? 'Yes' : 'No' }}.</p>
@endif

<h2 class="section">Persons involved</h2>
@if ($parties === [])
    <p class="muted small">No parties recorded.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Role</th>
                <th>Name</th>
                <th>Organization</th>
                <th>Involvement</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($parties as $party)
                <tr>
                    <td>{{ $party['kind_label'] }}</td>
                    <td>{{ $party['name'] ?: '—' }}</td>
                    <td>{{ $party['organization'] ?: '—' }}</td>
                    <td>{{ $party['involvement'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2 class="section">Additional notes</h2>
<div class="note">{{ filled($additional_notes) ? $additional_notes : 'No additional notes recorded.' }}</div>

<h2 class="section">Submission</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Submitted</span><span class="fact-value">{{ $submitted_at ?: '—' }}</span></td>
        <td><span class="fact-label">Submitted by</span><span class="fact-value">{{ $reporter_name }}</span></td>
    </tr>
</table>
