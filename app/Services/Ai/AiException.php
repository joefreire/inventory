<?php

namespace App\Services\Ai;

use RuntimeException;

/**
 * Falha ao falar com a IA (sem chave, todos os modelos falharam, resposta invalida).
 */
class AiException extends RuntimeException
{
}
