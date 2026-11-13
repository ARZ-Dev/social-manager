<div>
    <div class="card">
        <div class="card-header border-bottom d-flex justify-content-between">
            <h4 class="card-title mb-3">
               Messages List 
            </h4>
        </div>
        <div class="card-datatable table-responsive" wire:ignore>
            <table class="dataTable table border-top">
                <thead>
                    <tr>
                        <th>Id</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Message</th>
                        <th>Created_At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($contacts as $contact)
                        <tr>
                            <td>{{ $contact->id}}</td>
                            <td>{{ $contact->name}}</td>
                            <td>{{ $contact->email}}</td>
                            <td>{{ $contact->phone}}</td>
                            <td>{{ $contact->message}}</td>
                            <td>{{ $contact->created_at->format('d-m-Y h:i a')}}</td>
                            <td>
                                    <a href="#" class="text-body delete-record delete-button" data-id="{{ $contact->id }}">
                                        <i class="ti ti-trash ti-sm mx-2 text-danger"></i>
                                    </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @script
        @include('livewire.deleteConfirm')
    @endscript

</div>