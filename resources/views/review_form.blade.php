<form method="POST" id="review_form" action="{{ url()->current() }}">
	@csrf
	<input type="hidden" name="_limporter_phase" value="review" />
	<input type="hidden" name="_limporter_batch" value="{{ $data['upload_batch'] }}" />
	<div class="alert alert-info alert-block">
		Successfully uploaded and read {{ $data['upload_success_count'] }} entries
	</div>
	@if($data['upload_error_count'])
		<div class="alert alert-danger alert-block">
			There were errors for {{ $data['upload_error_count'] }} entries. <a href="{{ url()->current() }}?reviewerrors={{ $data['upload_error_reference'] }}" onclick="window.open(this.href); return false;">View entries</a>
		</div>
	@endif
	@include('limporter::review_form_fields', ['data' => $data])
	@include('limporter::review_form_options', ['data' => $data])
	<div class="row">
		<div class="col-12 col-md-6 col-xl-4 order-md-2 mt-3 mb-1 text-center text-md-left">
			<button type="submit" name="import" value="import" class="btn btn-primary btn-block">Import</button>
		</div>
		<div class="col-12 col-md-6 col-xl-4 order-md-1 offset-xl-2 mt-3 mb-1 text-center text-md-right">
			<button type="submit" name="delete" value="delete" class="btn btn-warning btn-block">Delete Upload</button>
		</div>
	</div>
</form>