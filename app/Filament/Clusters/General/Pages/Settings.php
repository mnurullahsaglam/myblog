<?php

namespace App\Filament\Clusters\General\Pages;

use App\Filament\Clusters\General;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;

class Settings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string $view = 'filament.clusters.general.pages.settings';

    protected static ?string $cluster = General::class;

    protected static ?string $title = 'Settings';

    protected static ?string $navigationLabel = 'Settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill($this->getSettingsData());
    }

    protected function getSettingsData(): array
    {
        $groups = ['site_info', 'meta', 'branding', 'social', 'contact'];
        $data = [];

        foreach ($groups as $group) {
            $settings = Setting::where('group', $group)->get();
            foreach ($settings as $setting) {
                $data[$group][$setting->name] = $setting->value;
            }
        }

        return $data;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('settings_tabs')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Site Information')
                            ->schema([
                                Forms\Components\TextInput::make('site_info.site_name')
                                    ->label('Site Name')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('site_info.site_description')
                                    ->label('Site Description')
                                    ->rows(3)
                                    ->maxLength(500),
                                Forms\Components\TextInput::make('site_info.site_url')
                                    ->label('Site URL')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('site_info.admin_email')
                                    ->label('Admin Email')
                                    ->email()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Tabs\Tab::make('Meta Tags')
                            ->schema([
                                Forms\Components\Textarea::make('meta.meta_title')
                                    ->label('Meta Title')
                                    ->rows(2)
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('meta.meta_description')
                                    ->label('Meta Description')
                                    ->rows(3)
                                    ->maxLength(160),
                                Forms\Components\TagsInput::make('meta.meta_keywords')
                                    ->label('Meta Keywords')
                                    ->placeholder('Add keywords...'),
                                Forms\Components\TextInput::make('meta.og_title')
                                    ->label('Open Graph Title')
                                    ->maxLength(255),
                                Forms\Components\Textarea::make('meta.og_description')
                                    ->label('Open Graph Description')
                                    ->rows(3)
                                    ->maxLength(300),
                                Forms\Components\FileUpload::make('meta.og_image')
                                    ->label('Open Graph Image')
                                    ->image()
                                    ->directory('settings/meta')
                                    ->visibility('public'),
                            ]),

                        Forms\Components\Tabs\Tab::make('Logos & Branding')
                            ->schema([
                                Forms\Components\FileUpload::make('branding.logo')
                                    ->label('Main Logo')
                                    ->image()
                                    ->directory('settings/branding')
                                    ->visibility('public')
                                    ->helperText('Recommended size: 200x60px'),
                                Forms\Components\FileUpload::make('branding.footer_logo')
                                    ->label('Footer Logo')
                                    ->image()
                                    ->directory('settings/branding')
                                    ->visibility('public')
                                    ->helperText('Recommended size: 150x45px'),
                                Forms\Components\FileUpload::make('branding.favicon')
                                    ->label('Favicon')
                                    ->image()
                                    ->directory('settings/branding')
                                    ->visibility('public')
                                    ->acceptedFileTypes(['image/x-icon', 'image/png'])
                                    ->helperText('Upload .ico or .png file (32x32px)'),
                                Forms\Components\ColorPicker::make('branding.primary_color')
                                    ->label('Primary Color')
                                    ->hex(),
                                Forms\Components\ColorPicker::make('branding.secondary_color')
                                    ->label('Secondary Color')
                                    ->hex(),
                            ]),

                        Forms\Components\Tabs\Tab::make('Social Media')
                            ->schema([
                                Forms\Components\TextInput::make('social.facebook_url')
                                    ->label('Facebook URL')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('social.twitter_url')
                                    ->label('Twitter URL')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('social.instagram_url')
                                    ->label('Instagram URL')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('social.linkedin_url')
                                    ->label('LinkedIn URL')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('social.youtube_url')
                                    ->label('YouTube URL')
                                    ->url()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('social.github_url')
                                    ->label('GitHub URL')
                                    ->url()
                                    ->maxLength(255),
                            ]),

                        Forms\Components\Tabs\Tab::make('Contact Information')
                            ->schema([
                                Forms\Components\TextInput::make('contact.phone')
                                    ->label('Phone Number')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('contact.address')
                                    ->label('Address')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('contact.city')
                                    ->label('City')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('contact.country')
                                    ->label('Country')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('contact.postal_code')
                                    ->label('Postal Code')
                                    ->maxLength(20),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            foreach ($data as $group => $settings) {
                if (is_array($settings)) {
                    foreach ($settings as $name => $value) {
                        if ($value !== null) {
                            $type = $this->getFieldType($group, $name);
                            Setting::set($group, $name, $value, $type);
                        }
                    }
                }
            }

            Notification::make()
                ->title('Settings saved successfully!')
                ->success()
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }

    protected function getFieldType(string $group, string $name): string
    {
        $fileFields = [
            'logo', 'footer_logo', 'favicon', 'og_image'
        ];

        if (in_array($name, $fileFields)) {
            return 'file';
        }

        if ($name === 'meta_keywords') {
            return 'json';
        }

        return 'text';
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->color('primary'),
        ];
    }
} 