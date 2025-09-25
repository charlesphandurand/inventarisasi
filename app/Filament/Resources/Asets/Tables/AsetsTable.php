<?php

namespace App\Filament\Resources\Asets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;

class AsetsTable
{
    public static function configure(Table $table): Table
    {
        $isAdmin = Auth::user()->hasRole('admin');

        return $table
            ->columns([
                TextColumn::make('nama_barang')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('jumlah_barang')
                    ->label('Jumlah Barang')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('nama_vendor')
                    ->label('Nama Vendor')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('harga')
                    ->label('Harga')
                    ->money('IDR', locale: 'id')
                    ->sortable(),
                TextColumn::make('lokasi')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('keterangan')
                    ->sortable()
                    ->searchable()
                    ->formatStateUsing(fn ($state) => strtoupper($state)),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make()->visible(fn () => $isAdmin),
            ])
            ->filters([
                Filter::make('lokasi')
                    ->label('Lokasi')
                    ->form([
                        \Filament\Forms\Components\Select::make('lokasi')
                            ->options(fn () => \App\Models\Aset::query()
                                ->whereNotNull('lokasi')
                                ->distinct()
                                ->pluck('lokasi', 'lokasi')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return isset($data['lokasi']) && $data['lokasi'] !== null
                            ? $query->where('lokasi', $data['lokasi'])
                            : $query;
                    }),
                Filter::make('nama_vendor')
                    ->label('Nama Vendor')
                    ->form([
                        \Filament\Forms\Components\Select::make('nama_vendor')
                            ->options(fn () => \App\Models\Aset::query()
                                ->whereNotNull('nama_vendor')
                                ->distinct()
                                ->pluck('nama_vendor', 'nama_vendor')
                                ->toArray())
                            ->searchable()
                            ->preload(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return isset($data['nama_vendor']) && $data['nama_vendor'] !== null
                            ? $query->where('nama_vendor', $data['nama_vendor'])
                            : $query;
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->visible(fn () => $isAdmin),
                ]),
            ]);
    }
}