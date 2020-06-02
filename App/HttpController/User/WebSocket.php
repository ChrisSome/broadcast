<?php


namespace App\HttpController\User;


use App\Base\FrontUserController;

class WebSocket extends FrontUserController
{
    public function index()
    {
        $this->render('front.websocket.index', [
            'server' => 'ws://192.168.254.103:9504'
        ]);
    }

}