<?php

declare(strict_types=1);

namespace App\Services\Delivery\Messenger;

use Illuminate\Support\Facades\Log;

class MessageFormatter
{
    /**
     * Подготовка сообщения: включение смайлов и очистка спец. символов.
     */
    public function prepareMessage(string $message) : string
    {
        if (empty($message)) {
            return $message;
        }

        $message = $this->processMessageForSmile($message);

        return strip_tags($message);
    }

    // ****************************************************************
    // *************************** Support ****************************
    // ****************************************************************

    /**
     * Обработка сообщения для корректного отображения смайлов:
     * literal-последовательности вида \uXXXX превращаются в реальные
     * символы через json_decode с экранированием остального.
     */
    protected function processMessageForSmile(string $message) : string
    {
        if (empty($message)) {
            return $message;
        }

        $start_message = $message;

        /**
         * Реальные управляющие байты и literal-последовательности
         * \u0000..\u001F (кроме \t, \n, \r) ломают json_decode — чистим.
         */
        $message = $this->removeControlCharacters($message);
        $message = $this->removeLiteralUnicodeControlSequences($message);

        $message = str_replace('\u0000', '', $message);
        $message = str_replace('\\', '\\\\', $message);
        $message = str_replace('\\\\u', '\\u', $message);
        $message = str_replace("\n", '\n', $message);
        $message = str_replace("\r", '\r', $message);
        $message = str_replace("\t", '\t', $message);
        $message = str_replace('"', '\"', $message);

        $message = json_decode('"'.$message.'"');

        if (empty($message)) {
            Log::error('Ошибка включения смайлов.', ['start_message' => $start_message]);

            return $start_message;
        }

        return $message;
    }

    /**
     * Удаление управляющих символов ASCII (кроме \n, \r, \t).
     */
    protected function removeControlCharacters(string $message) : string
    {
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $message);
    }

    /**
     * Удаление literal-последовательностей \u0000..\u001F
     * (кроме \u0009, \u000A, \u000D).
     */
    protected function removeLiteralUnicodeControlSequences(string $message) : string
    {
        return (string) preg_replace('/\\\\u000([0-8bcef]|1[0-9a-f])/i', '', $message);
    }
}
