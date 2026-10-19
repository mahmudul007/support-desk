<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ImportStatus;
use App\Jobs\ProcessTicketImport;
use App\Models\ImportRun;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    /**
     * Display a listing of imports and upload interface.
     */
    public function index(Request $request): View
    {
        $imports = ImportRun::query()->with('user')->latest('id')->paginate(10);

        return view('imports.index', [
            'imports' => $imports,
        ]);
    }

    /**
     * Store and queue a new CSV import job.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:20480'], // 20MB max
        ]);

        $uploadedFile = $request->file('file');
        $storedName = 'imports/'.Str::uuid().'.csv';
        $path = $uploadedFile->storeAs('', $storedName, 'local');

        $importRun = ImportRun::create([
            'user_id' => $request->user()->id,
            'original_filename' => $uploadedFile->getClientOriginalName(),
            'stored_path' => $path,
            'status' => ImportStatus::Pending,
        ]);

        // Dispatch background queued import job
        ProcessTicketImport::dispatch($importRun);

        return redirect()
            ->route('imports.show', $importRun)
            ->with('success', 'Import file uploaded and queued for processing.');
    }

    /**
     * View live progress counters of an import run.
     */
    public function show(ImportRun $import): View
    {
        $import->load(['errors' => fn ($q) => $q->take(50)]);

        return view('imports.show', [
            'import' => $import,
        ]);
    }

    /**
     * Download a CSV of rejected rows with specific row errors.
     */
    public function downloadErrors(ImportRun $import): StreamedResponse
    {
        $fileName = 'import_errors_'.$import->id.'.csv';

        return response()->streamDownload(function () use ($import): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Row Number', 'Raw Data', 'Error Messages']);

            $import->errors()->chunk(200, function ($errors) use ($handle): void {
                foreach ($errors as $error) {
                    fputcsv($handle, [
                        $error->row_number,
                        json_encode($error->raw_data),
                        json_encode($error->errors),
                    ]);
                }
            });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
