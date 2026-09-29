<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EventResource\Pages;
use App\Models\Event;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|\BackedEnum|null $navigationIcon = 'campus-calendar-star';

    protected static ?string $navigationLabel = 'Events';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(190),
            Textarea::make('description')->required()->columnSpanFull(),
            TextInput::make('category')->required()->maxLength(80),
            TextInput::make('location')->required()->maxLength(190),
            DateTimePicker::make('start_datetime')->required(),
            DateTimePicker::make('end_datetime')->required(),
            TextInput::make('capacity')->numeric()->required(),
            Select::make('status')->options([
                'published' => 'Published',
                'cancelled' => 'Cancelled',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable(),
                TextColumn::make('start_datetime')->dateTime()->sortable(),
                TextColumn::make('location'),
                TextColumn::make('status')->badge(),
                TextColumn::make('registrants_count')->counts('registrants')->label('Registered'),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('start_datetime', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEvents::route('/'),
            'edit' => Pages\EditEvent::route('/{record}/edit'),
        ];
    }
}
