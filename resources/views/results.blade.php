<h2>Import Results</h2>
<p>
	{!! implode('</p><p>', explode("\n", htmlspecialchars($data['report']))) !!}
</p>
<div class="border-top small muted">
	Upload Batch #{{ $data['upload_batch'] }}
</div>