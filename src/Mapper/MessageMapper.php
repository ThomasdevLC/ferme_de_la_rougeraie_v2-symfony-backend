<?php

namespace App\Mapper;

use App\Dto\Message\MessageDto;
use App\Entity\Message;

class MessageMapper
{
    public static function toDto(Message $message): MessageDto
    {
        return new MessageDto(
            id: $message->getId(),
            type: $message->getType()?->value,
            content: Message::normalizeContent($message->getContent() ?? ''),
            isActive: $message->isActive(),
        );
    }
}
