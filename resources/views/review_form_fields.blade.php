<div class="form-group mb-0">
	@if($data['upload_first_row'])
		<div class="alert alert-secondary text-truncate">
			<b>First row:</b> {{ $data['upload_first_row'] }}
		</div>
	@endif
	<input type="checkbox" name="skipfirstrow" id="skipfirstrow_check" {{ isset($data['upload_inputs']['hasheader']) ? 'checked="checked"' : '' }}>
	<label for="skipfirstrow_check">The first row is a header row (do not import)</label>
</div>
