<?php

namespace app\controllers;

use app\services\Dto\IngestTicketCommand;
use app\services\Exceptions\TicketValidationException;
use app\services\TicketProcessingService;
use Yii;

/**
 * HTTP-вход обработки тикета. `POST ticket/add` с JSON-телом тикета →
 * TicketProcessingService → стабильный JSON результата.
 */
class TicketController extends ApiController
{
    public function __construct(
        $id,
        $module,
        private readonly TicketProcessingService $processing,
        $config = [],
    ) {
        parent::__construct($id, $module, $config);
    }

    /**
     * Принять тикет из JSON-тела и прогнать через сервис.
     * 201 — обработан (стабильный JSON результата); 422 — невалидные/отсутствующие поля;
     * 500 — сбой раннтайма/модели/БД. Битый JSON отвергает парсер запроса (400).
     */
    public function actionAdd()
    {
        $data = Yii::$app->request->getBodyParams();

        try {
            $result = $this->processing->process(IngestTicketCommand::fromArray($data));
        } catch (TicketValidationException $e) {
            Yii::$app->response->statusCode = 422;

            return ['error' => 'invalid_ticket', 'details' => json_decode($e->getMessage(), true)];
        } catch (\Throwable $e) {
            Yii::error($e, __METHOD__);
            Yii::$app->response->statusCode = 500;

            return ['error' => 'processing_failed'];
        }

        Yii::$app->response->statusCode = 201;

        return $result;
    }
}
