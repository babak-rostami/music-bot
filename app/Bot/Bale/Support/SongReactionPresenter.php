<?php

namespace App\Bot\Bale\Support;

class SongReactionPresenter
{
    public static function messageText(): string
    {
        return "🎧 این آهنگ چطور بود؟\n\n" .
            "نظرت رو با یه لایک یا دیس لایک بگو 👇";
    }

    public static function keyboard(int $songId, int $likes, int $dislikes, ?string $userReaction): array
    {
        $likeText    = ($userReaction === 'like' ? '💚' : '🤍') . ' پسندیدم (' . self::formatCount($likes) . ')';
        $dislikeText = ($userReaction === 'dislike' ? '💔' : '🤍') . ' نپسندیدم (' . self::formatCount($dislikes) . ')';

        return [[
            ['text' => $likeText, 'callback_data' => "react_like_{$songId}"],
            ['text' => $dislikeText, 'callback_data' => "react_dislike_{$songId}"],
        ]];
    }

    public static function formatCount(int $count): string
    {
        if ($count >= 1_000_000) {
            return rtrim(rtrim(number_format($count / 1_000_000, 1), '0'), '.') . 'M';
        }

        if ($count >= 1000) {
            return rtrim(rtrim(number_format($count / 1000, 1), '0'), '.') . 'K';
        }

        return (string) $count;
    }
}
