<?php

namespace App\Filament\Resources\WhatsappContacts\Pages;

use App\Filament\Resources\WhatsappContacts\WhatsappContactResource;
use App\Filament\Resources\WhatsappMessages\WhatsappMessageResource;
use App\Models\WhatsappContact;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWhatsappContact extends ViewRecord
{
    protected static string $resource = WhatsappContactResource::class;

    protected string $view = 'filament.resources.whatsapp-contacts.pages.view-whatsapp-contact';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openConversation')
                ->label('فتح المحادثة')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('gray')
                ->url(fn (): string => $this->conversationUrl()),
            EditAction::make()->label('تعديل'),
        ];
    }

    public function getTitle(): string
    {
        return 'عرض جهة اتصال واتساب';
    }

    public function getSubheading(): ?string
    {
        return $this->getContact()->phone;
    }

    public function getContact(): WhatsappContact
    {
        /** @var WhatsappContact $record */
        $record = $this->getRecord();

        return $record->loadMissing(['department', 'user', 'latestMessage.task']);
    }

    /**
     * @return array{label:string,color:string}
     */
    public function statusMeta(): array
    {
        $contact = $this->getContact();

        return [
            'label' => $contact->statusLabel(),
            'color' => $contact->statusColor(),
        ];
    }

    public function conversationUrl(): string
    {
        return WhatsappMessageResource::getUrl('index', ['contact' => $this->getContact()->getKey()]);
    }
}
