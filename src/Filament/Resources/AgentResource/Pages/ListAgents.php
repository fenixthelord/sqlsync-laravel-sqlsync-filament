<?php

declare(strict_types=1);

namespace SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Http;
use SqlSync\FilamentSqlSync\Filament\Resources\AgentResource\AgentResource;
use SqlSync\LaravelSqlSync\Models\SyncAgent;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('اختبار الاتصال / Test Connection')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->action(function () {
                    $secret = config('sqlsync.agent.secret');

                    if (! $secret) {
                        Notification::make()
                            ->title('SQLSYNC_AGENT_SECRET غير معرّف بملف .env')
                            ->body('أضف المتغير ونظّف الكاش (config:clear) قبل إعادة المحاولة.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $agentId = 'filament-test-'.substr(md5((string) microtime(true)), 0, 8);
                    $timestamp = time();
                    $signature = hash_hmac('sha256', $agentId.'|'.$timestamp, $secret);

                    try {
                        $response = Http::withHeaders([
                            'X-Agent-ID' => $agentId,
                            'X-Agent-Token' => $signature,
                            'X-Timestamp' => $timestamp,
                        ])->post(route('sqlsync.agent.heartbeat'), []);
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('فشل الاتصال بالشبكة')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    if ($response->successful()) {
                        Notification::make()
                            ->title('الاتصال ناجح ✅')
                            ->body("تم إنشاء Agent تجريبي بنجاح: {$agentId}. هذا يؤكد أن الـ endpoint والـ secret مضبوطين صح. يمكنك الآن ربط الـ Windows Agent الحقيقي بنفس الـ secret.")
                            ->success()
                            ->send();

                        $this->resetTable();
                    } else {
                        Notification::make()
                            ->title('فشل الاتصال — كود: '.$response->status())
                            ->body($response->body())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('registerAgent')
                ->label('تسجيل Agent يدوياً')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->form([
                    TextInput::make('agent_id')
                        ->label('Agent ID')
                        ->required()
                        ->unique('sqlsync_agents', 'agent_id'),
                    TextInput::make('label')
                        ->label('اسم/وصف'),
                    TextInput::make('company_id')
                        ->label('Company ID')
                        ->numeric(),
                ])
                ->action(function (array $data) {
                    SyncAgent::create($data);

                    Notification::make()
                        ->title('تم تسجيل الـ Agent')
                        ->body('استخدم نفس الـ Agent ID و الـ secret ببرنامج الويندوز الآن.')
                        ->success()
                        ->send();

                    $this->resetTable();
                }),
        ];
    }
}
