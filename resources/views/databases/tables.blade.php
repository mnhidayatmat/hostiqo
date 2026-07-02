@extends('layouts.app')

@section('title', $database->name . ' - Tables')
@section('page-title', $database->name)
@section('page-description', 'Tables in this ' . $database->getTypeLabel() . ' database')

@section('page-actions')
    <a href="{{ route('databases.show', $database) }}" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Database
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <h5 class="card-title mb-3">
                <i class="bi bi-table me-1"></i> Tables
                <span class="badge badge-pastel-{{ $database->getTypeBadgeColor() }} ms-2">{{ count($tables) }}</span>
            </h5>
            <hr class="mt-0 mb-3">

            @if(count($tables) === 0)
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3 mb-0">This database has no tables.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Table</th>
                                <th class="text-end">Rows</th>
                                <th class="text-end">Size</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tables as $table)
                                <tr>
                                    <td>
                                        <a href="{{ route('databases.show-table', ['database' => $database, 'table' => $table->name]) }}"
                                           class="text-decoration-none">
                                            <i class="bi bi-table me-1 text-muted"></i>
                                            <code>{{ $table->name }}</code>
                                        </a>
                                    </td>
                                    <td class="text-end">{{ number_format((int) $table->rows) }}</td>
                                    <td class="text-end">{{ number_format((float) $table->size_mb, 2) }} MB</td>
                                    <td class="text-end">
                                        <a href="{{ route('databases.show-table', ['database' => $database, 'table' => $table->name]) }}"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye me-1"></i> View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-muted small mt-3 mb-0">
                    <i class="bi bi-info-circle me-1"></i>
                    Row counts are approximate as reported by the database engine.
                </p>
            @endif
        </div>
    </div>
@endsection
