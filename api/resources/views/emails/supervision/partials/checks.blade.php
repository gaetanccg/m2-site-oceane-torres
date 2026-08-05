@php
    $colors = ['ok' => '#2e7d32', 'degraded' => '#ed6c02', 'down' => '#c62828'];
    $icons = ['ok' => '✅', 'degraded' => '⚠️', 'down' => '❌'];
@endphp

<table role="presentation" style="width: 100%; border-collapse: collapse; margin: 10px 0 0 0;">
    @foreach ($snapshot->results as $key => $result)
        @php $color = $colors[$result->status->value] ?? '#666666'; @endphp
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #eeeeee;">
                <p style="margin: 0 0 4px 0; color: #333333; font-size: 15px;">
                    {{ $icons[$result->status->value] ?? '•' }}
                    <strong>{{ $key }}</strong>
                    <span style="color: {{ $color }}; font-size: 13px;">— {{ $result->status->value }}</span>
                </p>

                @if ($result->message)
                    <p style="margin: 0 0 4px 0; color: #555555; font-size: 14px; line-height: 1.5;">
                        {{ $result->message }}
                    </p>
                @endif

                @if ($result->details !== [])
                    <p style="margin: 0; color: #888888; font-size: 12px; font-family: monospace;">
                        @foreach ($result->details as $name => $value)
                            {{ $name }}={{ is_null($value) ? 'null' : (is_bool($value) ? ($value ? 'true' : 'false') : $value) }}@if (! $loop->last) · @endif
                        @endforeach
                    </p>
                @endif
            </td>
        </tr>
    @endforeach
</table>
