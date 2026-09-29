<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('full_name')->required()->maxLength(150),
            TextInput::make('email')->email()->required()->maxLength(190),
            Select::make('role')->options([
                'student' => 'Student',
                'faculty' => 'Faculty',
                'official' => 'Official',
                'admin' => 'Admin',
            ])->required(),
            TextInput::make('authority_level')->maxLength(60),
            Select::make('status')->options([
                'active' => 'Active',
                'pending' => 'Pending',
                'suspended' => 'Suspended',
            ])->required(),
            Toggle::make('is_verified')->label('Verified'),
            Toggle::make('must_change_password')->label('Force password change'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state) => match ($state) {
                        'active' => 'success',
                        'pending' => 'warning',
                        default => 'danger',
                    }),
                IconColumn::make('is_verified')->boolean()->label('Verified'),
                TextColumn::make('department')->toggleable(),
                TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    'student' => 'Student',
                    'faculty' => 'Faculty',
                    'official' => 'Official',
                    'admin' => 'Admin',
                ]),
                SelectFilter::make('status')->options([
                    'active' => 'Active',
                    'pending' => 'Pending',
                    'suspended' => 'Suspended',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
