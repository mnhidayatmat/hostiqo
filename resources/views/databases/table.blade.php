@extends('layouts.app')

@section('title', $table . ' - ' . $database->name)
@section('page-title', $table)
@section('page-description', 'Table in ' . $database->name)

@section('page-actions')
    <a href="{{ route('databases.tables', $database) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Tables
    </a>
@endsection

@section('content')
    {{-- Columns / Structure --}}
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-diagram-3 me-1"></i> Structure
                <span class="badge badge-pastel-{{ $database->getTypeBadgeColor() }} ms-2">{{ count($columns) }} columns</span>
            </h5>
            <hr class="mt-0 mb-3">

            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Column</th>
                            <th>Type</th>
                            <th>Nullable</th>
                            <th>Key</th>
                            <th>Default</th>
                            <th>Extra</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($columns as $column)
                            <tr>
                                <td><code>{{ $column->name }}</code></td>
                                <td class="text-muted">{{ $column->type }}</td>
                                <td>
                                    @if(strtoupper((string) $column->nullable) === 'YES')
                                        <span class="badge badge-pastel-green">YES</span>
                                    @else
                                        <span class="badge badge-pastel-red">NO</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!empty($column->key))
                                        <span class="badge badge-pastel-blue">{{ $column->key }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($column->default === null)
                                        <span class="text-muted fst-italic">NULL</span>
                                    @else
                                        <code>{{ $column->default }}</code>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $column->extra }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Data / Rows --}}
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-table me-1"></i> Data
                <span class="badge badge-pastel-{{ $database->getTypeBadgeColor() }} ms-2">{{ number_format($rows->total()) }} rows</span>
            </h5>
            <hr class="mt-0 mb-3">

            @if($rows->total() === 0)
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-0">This table is empty.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-0" style="white-space: nowrap;">
                        <thead class="table-light">
                            <tr>
                                @foreach($columns as $column)
                                    <th>{{ $column->name }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($rows as $row)
                                <tr>
                                    @foreach($columns as $column)
                                        @php($value = ((array) $row)[$column->name] ?? null)
                                        <td style="max-width: 320px; overflow: hidden; text-overflow: ellipsis;">
                                            @if($value === null)
                                                <span class="text-muted fst-italic">NULL</span>
                                            @else
                                                <span title="{{ $value }}">{{ Str::limit((string) $value, 120) }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center mt-3">
                    <span class="text-muted small">
                        Showing {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ number_format($rows->total()) }}
                    </span>
                    {{ $rows->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
