@extends('pdf.layout')

@section('title', $type.' Report')
@section('ref', $type.' Report')

@section('meta')
  Generated <strong>{{ $generated_at }}</strong><br>
  Records <strong>{{ count($rows) }}</strong><br>
  <span class="pill pill-grey">CONFIDENTIAL</span>
@endsection

@section('content')
  @php($kpis = collect($summary ?? [])->reject(fn ($v) => is_array($v)))
  @if($kpis->isNotEmpty())
    <table class="stats" style="margin-top: 14px;">
      @foreach($kpis->chunk(4) as $chunk)
        <tr>
          @foreach($chunk as $key => $value)
            <td>
              <div class="stat-label">{{ ucwords(str_replace('_', ' ', $key)) }}</div>
              <div class="stat-value">{{ is_int($value) ? number_format($value) : (is_numeric($value) ? number_format((float) $value, 2) : $value) }}</div>
            </td>
          @endforeach
        </tr>
      @endforeach
    </table>
  @endif

  <div class="section">Details</div>
  @if(!empty($rows))
    <table class="grid">
      <thead>
        <tr>
          @foreach((array) $rows[0] as $col => $sample)
            <th class="{{ is_numeric($sample) ? 'num' : '' }}">{{ ucwords(str_replace('_', ' ', $col)) }}</th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @foreach($rows as $row)
          <tr class="{{ $loop->even ? 'alt' : '' }}">
            @foreach((array) $row as $value)
              <td class="{{ is_numeric($value) ? 'num' : '' }}">{{ $value ?? '—' }}</td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  @else
    <p class="muted center" style="padding: 20px;">No data for this report.</p>
  @endif
@endsection
