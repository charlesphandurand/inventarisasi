<x-filament::page>
	<div class="space-y-6">
		{{-- Info text (tampil di atas pratinjau) --}}
		@if($this->infoText)
			<div class="text-sm text-gray-200">
				{{ $this->infoText }}
			</div>
		@endif

		{{ $this->form }}

		@php
			$url = $this->getDownloadUrl();
		@endphp

		<div class="flex items-center justify-between">
			@if($url)
				<p class="text-sm text-gray-400">Dokumen bantuan tersedia untuk diunduh.</p>
			@endif
			<div class="flex items-center gap-2">
				@if($url)
					<x-filament::button tag="a" href="{{ $url }}" target="_blank" color="success" icon="heroicon-m-arrow-down-tray" size="sm">Unduh PDF</x-filament::button>
				@endif
				@if(auth()->user()?->hasAnyRole(['maker', 'approver']))
					<x-filament::button wire:click="deleteFile" color="danger" icon="heroicon-m-trash" size="sm">Hapus</x-filament::button>
				@endif
			</div>
		</div>
	</div>
</x-filament::page>

