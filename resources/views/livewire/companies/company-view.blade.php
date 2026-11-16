<div>
    <div class="card">
        <div class="card-header border-bottom d-flex justify-content-between">
            <h4 class="card-title mb-3">Company List</h4>
            @if(auth()->user()->can('company-create') && auth()->user()->canAccessAllCompanies())
                <a class="btn btn-primary h-50" href="{{ route('companies.create') }}">Add Company</a>
            @endif
        </div>
        <div class="card-datatable table-responsive">
            <table class="dataTable table border-top">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>E-mail</th>
                        <th>Phone</th>
                        <th>Branches</th>
                        <th>Users</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($companies as $company)
                        <tr>
                            <td>{{ $company->id }}</td>
                            <td>{{ $company->name }}</td>
                            <td>{{ $company->email ?? '-' }}</td>
                            <td>{{ $company->phone ?? '-' }}</td>
                            <td>{{ $company->branches_count }}</td>
                            <td>{{ $company->users_count }}</td>
                            <td>
                                <span class="badge {{ $company->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
                                    {{ $company->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td>
                                @can('branch-list')
                                    <a href="{{ route('companies.branches', $company->id) }}" class="text-body" title="Branches"><i class="ti ti-building-store ti-sm me-2"></i></a>
                                @endcan
                                @can('company-edit')
                                    <a href="{{ route('companies.edit', $company->id) }}" class="text-body" title="Edit"><i class="ti ti-edit ti-sm me-2"></i></a>
                                @endcan
                                @can('company-delete')
                                    <a href="#" class="text-body delete-button" data-id="{{ $company->id }}" title="Delete"><i class="ti ti-trash ti-sm mx-2 text-danger"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">No companies yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @script
        @include('livewire.deleteConfirm')
    @endscript
</div>
