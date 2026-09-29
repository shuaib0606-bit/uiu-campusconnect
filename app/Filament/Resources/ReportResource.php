<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportResource\Pages;
use App\Models\Report;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-flag';

    protected static ?string $navigationLabel = 'Post Reports';

    protected static string|\UnitEnum|null $navigationGroup = 'Moderation';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->options([
                'open' => 'Open',
                'reviewing' => 'Reviewing',
                'resolved' => 'Resolved',
                'rejected' => 'Rejected',
            ])->required(),
            Textarea::make('moderator_note')->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reporter.full_name')->label('Reporter')->searchable(),
                TextColumn::make('post.body')->label('Post')->limit(60),
                TextColumn::make('reason')->limit(60)
                    ->description(fn ($record) => $record->details ? \Illuminate\Support\Str::limit($record->details, 80) : null),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'danger',
                        'reviewing' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'open' => 'Open',
                    'reviewing' => 'Reviewing',
                    'resolved' => 'Resolved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->recordActions([EditAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReports::route('/'),
            'edit' => Pages\EditReport::route('/{record}/edit'),
        ];
    }
}
