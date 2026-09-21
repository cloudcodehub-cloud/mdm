@if ($tasks === [])
    <p class="muted small">{{ $empty }}</p>
@else
    <table class="rows">
        <thead>
            <tr>
                <th>Task</th>
                <th>Flags</th>
                @if ($mode === 'skipped')
                    <th>DSP reason</th>
                @else
                    <th>Notes</th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach ($tasks as $task)
                <tr>
                    <td>
                        {{ $task['title'] }}
                        @if ($task['is_one_off'])
                            <div class="small muted">Visit-only</div>
                        @endif
                    </td>
                    <td>
                        @if ($task['is_required']) <span class="badge">Required</span> @endif
                        @if ($task['is_critical']) <span class="badge">Critical</span> @endif
                    </td>
                    <td>
                        @if ($mode === 'skipped')
                            {{ $task['skip_reason'] ?: '—' }}
                            @if (filled($task['skip_comment']))
                                <div class="small">{{ $task['skip_comment'] }}</div>
                            @endif
                        @else
                            {{ filled($task['completion_note']) ? $task['completion_note'] : '—' }}
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
