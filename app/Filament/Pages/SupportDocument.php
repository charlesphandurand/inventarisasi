<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;
use Joaopaulolndev\FilamentPdfViewer\Forms\Components\PdfViewerField;
use UnitEnum;
use BackedEnum;
use Illuminate\Support\HtmlString;

class SupportDocument extends Page implements HasForms
{
	use InteractsWithForms;

	protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-question-mark-circle';
	protected static ?string $navigationLabel = 'Bantuan/Support';
	protected static ?string $title = 'Bantuan/Support';
	protected static UnitEnum|string|null $navigationGroup = null;
	protected static ?int $navigationSort = 9999; // paling bawah

	protected string $view = 'filament.pages.support-document';

	public ?array $data = [];
	public ?string $infoText = null;

	public static function shouldRegisterNavigation(): bool
	{
		return true;
	}

	public function mount(): void
	{
		$this->form->fill([
			'file' => $this->getLatestPdfPath(),
			'info_text' => null,
		]);

		// muat info text dari storage publik jika ada
		if (Storage::disk('public')->exists('support/info.txt')) {
			$this->infoText = trim(Storage::disk('public')->get('support/info.txt'));
		} else {
			$this->infoText = 'Kontak maker +62859035252';
		}
	}

	protected function getFormSchema(): array
	{
		return [
			// Info yang bisa diedit maker (disimpan via tombol)
			Forms\Components\Textarea::make('info_text')
				->rows(3)
				->maxLength(500)
				->default(fn () => $this->infoText)
				->visible(fn () => Auth::user()?->hasAnyRole(['maker', 'approver']) ?? false),
			Forms\Components\Placeholder::make('save_info_btn')
				->label('')
				->hiddenLabel()
				->content(fn () => new HtmlString(view('filament.components.support-info-save')->render()))
				->visible(fn () => Auth::user()?->hasAnyRole(['maker', 'approver']) ?? false),
			Forms\Components\FileUpload::make('file')
				->label('Upload PDF Bantuan')
				->acceptedFileTypes(['application/pdf'])
				->multiple(false)
				->disk('public')
				->directory('pdfs')
				->preserveFilenames()
				->visibility('public')
				->maxSize(5120)
				->helperText('Ukuran maksimal 5MB')
				->visible(fn () => Auth::user()?->hasAnyRole(['maker', 'approver']) ?? false)
				->saveUploadedFileUsing(function ($file) {
					if ($file instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile || $file instanceof \Livewire\TemporaryUploadedFile) {
						$filename = $file->getClientOriginalName();
						$path = $file->storeAs('pdfs', $filename, 'public');
						try { $file->delete(); } catch (\Throwable $e) {}
						Notification::make()->title('Upload berhasil')->success()->send();
						return $path;
					}
					return $file;
				}),

			PdfViewerField::make('preview')
				->label('Pratinjau')
				->minHeight('75vh')
				->fileUrl(function ($get) {
					$value = $get('file');
					if (! $value) return null;
					$disk = Storage::disk('public');
					$base = $disk->url($value);
					$version = $disk->exists($value) ? $disk->lastModified($value) : time();
					return $base . '?v=' . $version; // cache buster agar tidak perlu refresh
				})
				->visible(fn ($get) => !empty($get('file'))),
		];
	}

	protected function getFormStatePath(): string
	{
		return 'data';
	}

	public function getDownloadUrl(): ?string
	{
		$file = $this->form->getState()['file'] ?? null;
		return $file ? Storage::disk('public')->url($file) : null;
	}

	public function deleteFile(): void
	{
		if (!Auth::user()?->hasAnyRole(['maker', 'approver'])) {
			return;
		}
		$file = $this->form->getState()['file'] ?? null;
		if ($file && Storage::disk('public')->exists($file)) {
			Storage::disk('public')->delete($file);
			$this->form->fill(['file' => null]);
			Notification::make()->title('Dokumen dihapus')->success()->send();
		}
	}

	public function saveInfo(): void
	{
		if (!Auth::user()?->hasAnyRole(['maker', 'approver'])) {
			return;
		}
		$state = $this->form->getState();
		$text = (string)($state['info_text'] ?? '');
		$this->infoText = $text;
		Storage::disk('public')->put('support/info.txt', $text);
		Notification::make()->title('Informasi disimpan')->success()->send();
	}

	private function getLatestPdfPath(): ?string
	{
		$files = collect(Storage::disk('public')->files('pdfs'))
			->filter(fn ($p) => str_ends_with(strtolower($p), '.pdf'))
			->map(fn ($p) => ['p' => $p, 't' => Storage::disk('public')->lastModified($p)])
			->sortByDesc('t')
			->values();
		return $files->first()['p'] ?? null;
	}
}
