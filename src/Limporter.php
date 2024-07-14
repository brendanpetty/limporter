<?php

namespace Brendanpetty\Limporter;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class Limporter
{
    /**
     * The general controller management for all the routes, which calls back to specific methods defined in the controller where this is called from
	 * $options['maxFileSizeMB'] = (int) default is 10
     *
     * @return void
     */
    public static function controller(Request $request, $callbacks, $options = [])
    {
        if($request->isMethod('get')) {
			if($request->input('reviewerrors')) {
				$attemptReference = preg_replace('/[^-batch0-9]/', '', $request->input('reviewerrors'));
				if($uploadErrors = static::getUploadErrors($attemptReference)) {
					header('Content-type: text/plain');
					header('Content-disposition: attachment; filename="upload-errors.txt"');
					echo $uploadErrors;
					exit();
				}
				throw new NotFoundHttpException();
			}
			return null;	// default upload initial form, if a simple get
		}
		
		if(!$request->isMethod('post'))
			throw new MethodNotAllowedHttpException(['get', 'post']);

		switch($request->input('_limporter_phase')) {
			case 'upload':
				if(!$request->hasFile('file') || !$request->file('file')->isValid())
					return ['redirect' => redirect()->back()->with('info', 'No file submitted')];

				if(strtolower($request->file->getClientOriginalExtension()) != 'csv')		// checks client file extension (not safe, just simple check)
					return ['redirect' => redirect()->back()->with('error', 'Invalid file extension (' . $request->file->getClientOriginalExtension() . ') - must be .csv')];
				
				$maxFileSizeMB = 10;	// default
				if(is_array($options) && array_key_exists('maxFileSizeMB', $options))
					$maxFileSizeMB = $options['maxFileSizeMB'];
				if($request->file->getSize() > (1024 * 1024 * $maxFileSizeMB))		// file size limit
					return ['redirect' => redirect()->back()->with('error', 'File too large - maximum ' . $maxFileSizeMB . 'MB')];

				$location = 'uploads';
				$filename = $request->file->store($location);
				$filepath = storage_path('app/' . $filename);
				$filehandle = fopen($filepath, 'r');

				$batchNumber = LimporterLine::getNextUploadBatchNumber();
				$uploadReference = date('Ymd-His-') . 'batch' . $batchNumber;
				$maxCharactersInLine = 100000;

				$i = 0;
				$errorCount = 0;
				$currentFilePosition = ftell($filehandle);
				while(($linedata = fgetcsv($filehandle, $maxCharactersInLine, ',')) !== false) {
					$line_json = json_encode($linedata);
					if($line_json === FALSE) {
						$lineWithError = static::readFromFileAndResetFilePointer($filehandle, $currentFilePosition, $maxCharactersInLine);
						static::writeUploadLineError($uploadReference, $lineWithError, 'Invalid characters', $errorCount);
					} else {
						$line = new LimporterLine([
							'is_header_row' => ($i++ == 0),	// first row (header?)
							'row_data' => $line_json,
							'upload_batch' => $batchNumber,
						]);
						try {
							$line->save();
						} catch(\Exception $e) {
							$lineWithError = static::readFromFileAndResetFilePointer($filehandle, $currentFilePosition, $maxCharactersInLine);
							static::writeUploadLineError($uploadReference, $lineWithError, 'Database error (' . $e->getMessage() . ')', $errorCount);
						}
					}
					$currentFilePosition = ftell($filehandle);
				}
				fclose($filehandle);
				unlink($filepath);
				
				$firstRow = LimporterLine::where('upload_batch', $batchNumber)->where('is_header_row', true)->first();
				if($firstRow) {
					$firstRowSummary = implode(', ', json_decode($firstRow->row_data, true) ?? ['Error previewing first row!']);
				}

				return [
					'phase' => 'review',
					'upload_error_count' => $errorCount,
					'upload_batch' => $batchNumber,
					'upload_error_reference' => $uploadReference,
					'upload_success_count' => $i,
					'upload_first_row' => $firstRowSummary ?? null,
					'upload_inputs' => $request->input(),
				];
			case 'review':
				if(($request->input('delete') == 'delete') && $request->input('_limporter_batch')) {
					LimporterLine::where('upload_batch', $request->input('_limporter_batch'))->delete();
					// should also delete any upload error files
					return null;
				}
				
				if(!$request->input('_limporter_batch'))
					return ['redirect' => redirect()->back()->with('error', 'Missing batch number - cannot process upload!')];
				
				if(!array_key_exists('importLine', $callbacks) || !is_callable($callbacks['importLine']))
					return ['redirect' => redirect()->back()->with('error', 'No import method is defined - cannot process upload!')];
				
				if($request->input('skipfirstrow'))	// delete the first row, if it was just a header
					LimporterLine::where('upload_batch', $request->input('_limporter_batch'))->where('is_header_row', true)->delete();
				
				$meta = ['requestInputs' => $request->input()];
				$entries = LimporterLine::where('upload_batch', $request->input('_limporter_batch'))->get();
				$results = ['total' => [
								'success' => 0,
								'error' => 0,
							], 'other' => []];
				foreach($entries as $entry) {
					try {
						$result = $callbacks['importLine'](json_decode($entry->row_data, true), $entry, $meta);
						
						foreach($result as $key => $value) {
							if($key == 'success')
								$results['total']['success'] += $value;
							else if($key == 'error')
								$results['total']['error'] += $value;
							else if(substr($key, -6) == '_count') {
								if(!isset($results['other'][substr($key, 0, -6)]['count']))
									$results['other'][substr($key, 0, -6)]['count'] = 0;
								$results['other'][substr($key, 0, -6)]['count'] += $value;
							} else if(substr($key, -5) == '_text')
								$results['other'][substr($key, 0, -5)]['text'][] = $value;
							else
								$results['other']['_other'][$key][] = $value;
						}
						
						$entry->delete();
					} catch(\Exception $e) {
						$results['total']['error'] += 1;
						Log::info('Caught Limport Exception');
						Log::info($e);
						dd($e);
					}
				}
				
				$report = '';
				if($results['total']['success'])
					$report .= ($report ? "\n" : '') . 'Successully imported ' . $results['total']['success'] . ' entr' . ($results['total']['success'] == 1 ? 'y' : 'ies') . '.';
				if($results['total']['error'])
					$report .= ($report ? "\n" : '') . 'Could not import ' . $results['total']['error'] . ' entr' . ($results['total']['error'] == 1 ? 'y' : 'ies') . '.';
				foreach($results['other'] as $otherName => $otherValues) {
					if($otherName == '_other')
						continue;
					$report .= ($report ? "\n" : '') . Str::of($otherName)->camel() . ':';
					if(isset($otherValues['count']))
						$report .= ' ' . $otherValues['count'];
					if(isset($otherValues['text']))
						$report .= ' (' . implode(', ', $otherValues['text']) . ')';
				}
				if(isset($results['other']['_other'])) {
					foreach($results['other']['_other'] as $key => $values) {
						$report .= ($report ? "\n" : '') . $key . ':' . "\n\t" . implode("\n\t", $values);
					}
				}
				
				return [
					'phase' => 'results',
					'report' => $report,
					'upload_batch' => $request->input('_limporter_batch'),
				];
			default:
				throw new NotFoundHttpException();
		}
		
		return ['redirect' => redirect()->back()->with('info', 'Nothing to import yet')];
    }
	
	private static function readFromFileAndResetFilePointer($filehandle, $currentFilePosition, $maxCharactersInLine) {
		$newFilePosition = ftell($filehandle);
		fseek($filehandle, $currentFilePosition);
		$lineWithError = fgets($filehandle, $maxCharactersInLine);
		fseek($filehandle, $newFilePosition);
		return $lineWithError;
	}
	
	private static function writeUploadLineError($uploadReference, $line, $error, &$errorCount) {
		Storage::append('uploaderrors/' . $uploadReference . '.txt', '[' . date('r') . '] ' . $error . ':' . "\n" . trim($line) . "\n");
		$errorCount++;
	}

	private static function getUploadErrors($uploadReference) {
		if(!Storage::exists('uploaderrors/' . $uploadReference . '.txt'))
			return null;
		return Storage::get('uploaderrors/' . $uploadReference . '.txt');
	}
}