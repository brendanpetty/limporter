@if(is_null($data))
	@include('limporter::upload_form', ['data' => $data])
@endif
@if(is_array($data) && isset($data['phase']) && ($data['phase'] == 'review'))
	@include('limporter::review_form', ['data' => $data])
@endif
@if(is_array($data) && isset($data['phase']) && ($data['phase'] == 'results'))
	@include('limporter::results', ['data' => $data])
@endif