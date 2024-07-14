<form method="POST" id="upload_form" action="{{ url()->current() }}" enctype="multipart/form-data">
	@csrf
	<input type="hidden" name="_limporter_phase" value="upload" />
	@include('limporter::upload_form_fields', ['data' => $data])
	@include('limporter::upload_form_options', ['data' => $data])
	<div class="col-md-12 col-xl-4 offset-xl-4 mt-3 mb-1 text-center">
		<button type="submit" class="btn btn-primary btn-block">Upload</button>
	</div>
</form>