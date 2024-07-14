<?php

namespace Brendanpetty\Limporter;

use Illuminate\Database\Eloquent\Model;

class LimporterLine extends Model
{
	protected $table = 'limporter';
	protected $fillable = ['is_header_row', 'row_data', 'upload_batch'];
	
	public static function getNextUploadBatchNumber() {
		return (LimporterLine::max('upload_batch') ?? 0) + 1;
	}
}
