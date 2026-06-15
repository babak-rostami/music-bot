<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class TelBotController extends Controller
{
    public function index(Request $request)
    {
        $text = $request->input('message.text');
        $chatId = $request->input('message.chat.id');

        Http::post("https://api.telegram.org/bot8934572332:AAEL9Cxt-HG0nE7XU_7VBh8d5t6yrrqfMeA/sendMessage", [
            'chat_id' => $chatId,
            'text' => 'پیام دریافت شد' . $text
        ]);
    }
}
