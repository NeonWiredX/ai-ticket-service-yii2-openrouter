<?php

namespace app\controllers;

use yii\filters\ContentNegotiator;
use yii\filters\Cors;
use yii\web\Response;

abstract class ApiController extends \yii\rest\Controller
{
    public function behaviors()
    {
        return [
            'corsFilter' => [
                'class' => Cors::class,
                'cors' => [
                    'Origin' => ['*'], //На время тестирования разрешаем доступ всем источникам
                    'Access-Control-Request-Method' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                    'Access-Control-Request-Headers' => ['*'], // эхо запрошенных заголовков, включая Authorization
                    'Access-Control-Max-Age' => 86400,
                ],
            ],
            'contentNegotiator' => [
                'class' => ContentNegotiator::class,
                'formats' => ['application/json' => Response::FORMAT_JSON],
            ],
        ];
    }

}