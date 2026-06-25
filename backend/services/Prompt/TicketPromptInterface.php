<?php

namespace app\services\Prompt;

use app\models\Entity\Ticket;

/**
 * Версионированный промпт классификации (инструкция модели).
 * Версионируется наравне со схемой и политикой; версия оседает в AiDecision.prompt_version.
 */
interface TicketPromptInterface
{
    public function getVersion(): string;

    public function getSystemPrompt(): string;

    public function renderUserPrompt(Ticket $ticket): string;
}
