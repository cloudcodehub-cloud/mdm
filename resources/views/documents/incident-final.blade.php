@include('documents.partials.incident-reporter')

<h2 class="section">Management acknowledgment</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Acknowledged</span><span class="fact-value">{{ $acknowledged_at ?: '—' }}</span></td>
        <td><span class="fact-label">Acknowledged by</span><span class="fact-value">{{ $acknowledged_by_name ?: '—' }}</span></td>
    </tr>
</table>

<h2 class="section">Official classification</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Official category</span><span class="fact-value">{{ $official_category ?: '—' }}</span></td>
        <td><span class="fact-label">Official subcategory</span><span class="fact-value">{{ $official_subcategory ?: '—' }}</span></td>
    </tr>
</table>

<h2 class="section">Management review</h2>
@if ($reviews === [])
    <p class="muted small">No management review entries recorded.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Reviewer</th>
                <th>Recorded</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reviews as $review)
                <tr>
                    <td>{{ $review['reviewer_name'] ?: '—' }}</td>
                    <td>{{ $review['created_at'] ?: '—' }}</td>
                    <td style="white-space:pre-wrap;">{{ $review['note'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2 class="section">Finding</h2>
<div class="note">{{ filled($finding) ? $finding : 'No finding recorded.' }}</div>

<h2 class="section">Cause &amp; contributing factors</h2>
<div class="note">{{ filled($cause_contributing_factors) ? $cause_contributing_factors : 'No cause recorded.' }}</div>

<h2 class="section">Prevention plan</h2>
<div class="note">{{ filled($prevention_plan) ? $prevention_plan : 'No prevention plan recorded.' }}</div>

<h2 class="section">Follow-ups</h2>
@if ($follow_ups === [])
    <p class="muted small">No follow-ups recorded.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Action</th>
                <th>Assigned to</th>
                <th>Due</th>
                <th>Status</th>
                <th>Completion</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($follow_ups as $followUp)
                <tr>
                    <td>{{ $followUp['action_type'] }}</td>
                    <td>{{ $followUp['assignee_name'] ?: '—' }}</td>
                    <td>{{ $followUp['due_on'] ?: '—' }}</td>
                    <td><span class="badge">{{ $followUp['status_label'] }}</span></td>
                    <td>
                        @if (filled($followUp['completed_at']))
                            {{ $followUp['completed_at'] }}
                            @if (filled($followUp['completed_by_name'])) ({{ $followUp['completed_by_name'] }}) @endif
                        @endif
                        @if (filled($followUp['completion_note']))
                            <div class="small" style="margin-top:3px;">{{ $followUp['completion_note'] }}</div>
                        @elseif (! filled($followUp['completed_at']))
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2 class="section">Formal reporting records</h2>
@if ($reporting_records === [])
    <p class="muted small">No formal reporting records.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Reported to</th>
                <th>When / by</th>
                <th>Category / method</th>
                <th>Reference</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($reporting_records as $record)
                <tr>
                    <td>
                        {{ $record['reported_to'] }}
                        @if (filled($record['person_organization']))
                            <div class="small muted">{{ $record['person_organization'] }}</div>
                        @endif
                    </td>
                    <td>
                        {{ $record['reported_at'] ?: '—' }}
                        @if (filled($record['reported_by_name']))
                            <div class="small muted">{{ $record['reported_by_name'] }}</div>
                        @endif
                    </td>
                    <td>{{ collect([$record['category'], $record['method']])->filter()->implode(' · ') ?: '—' }}</td>
                    <td>{{ $record['reference_number'] ?: '—' }}</td>
                    <td>{{ $record['note'] ?: '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<h2 class="section">Amendments</h2>
@if ($amendments === [])
    <p class="muted small">No amendments recorded.</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Field</th>
                <th>Reason</th>
                <th>Previous</th>
                <th>Amended to</th>
                <th>By</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($amendments as $amendment)
                <tr>
                    <td>{{ $amendment['field_label'] }}</td>
                    <td>{{ $amendment['reason'] }}</td>
                    <td style="white-space:pre-wrap;">{{ $amendment['original_content'] ?: '—' }}</td>
                    <td style="white-space:pre-wrap;">{{ $amendment['new_content'] }}</td>
                    <td>
                        {{ $amendment['author_name'] ?: '—' }}
                        <div class="small muted">{{ $amendment['created_at'] ?: '' }}</div>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="small muted">Current values above reflect the latest amendment; prior content is preserved in this log.</p>
@endif

<h2 class="section">Closure</h2>
<table class="facts">
    <tr>
        <td><span class="fact-label">Closed</span><span class="fact-value">{{ $closed_at ?: '—' }}</span></td>
        <td><span class="fact-label">Closed by</span><span class="fact-value">{{ $closed_by_name ?: '—' }}</span></td>
    </tr>
</table>
<div class="note">{{ filled($closure_note) ? $closure_note : 'No closure note recorded.' }}</div>
