<?php

namespace app\services\Prompt;

use app\models\Entity\Ticket;

/**
 * Промпт классификации, версия 1. Сам промпт — инструкция модели; формат ответа задаёт
 * отдельная схема (structured output). Версия оседает в AiDecision.prompt_version
 * для воспроизводимости решения.
 */
class ClassificationPromptV1 implements TicketPromptInterface
{
    public const VERSION = 'prompt.v1';

    public function getVersion(): string
    {
        return self::VERSION;
    }

    public function getSystemPrompt(): string
    {
        return <<<TXT
Ты — классификатор обращений в поддержку. По тексту тикета определи категорию, приоритет,
риск и предлагаемый маршрут. Отвечай строго по предоставленной JSON-схеме, без пояснений.
Если данных недостаточно — выбирай наиболее консервативный (безопасный) вариант риска.
TXT;
    }

    public function renderUserPrompt(Ticket $ticket): string
    {
        return <<<TXT
Тема: {$ticket->subject}

Текст обращения:
{$ticket->body}
TXT;
    }
}
