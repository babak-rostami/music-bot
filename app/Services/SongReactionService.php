<?php

namespace App\Services;

use App\Models\Song;
use App\Models\SongReaction;
use Illuminate\Support\Facades\DB;

class SongReactionService
{
    /**
     * @return array{likes_count:int, dislikes_count:int, user_reaction:?string, action:string}
     */
    public function handleReaction(Song $song, string $type, string $identityType, string $identityId): array
    {
        return DB::transaction(function () use ($song, $type, $identityType, $identityId) {
            $existing = SongReaction::where('song_id', $song->id)
                ->where('identity_type', $identityType)
                ->where('identity_id', $identityId)
                ->lockForUpdate()
                ->first();

            $action = 'added';
            $userReaction = $type;

            if ($existing && $existing->type === $type) {
                // دوباره روی همون دکمه زده → برداشتن واکنش
                $existing->delete();
                $song->decrement($type === 'like' ? 'likes_count' : 'dislikes_count');
                $action = 'removed';
                $userReaction = null;
            } elseif ($existing) {
                // از یکی به اون یکی سوییچ کرده
                $oldType = $existing->type;
                $existing->update(['type' => $type]);
                $song->decrement($oldType === 'like' ? 'likes_count' : 'dislikes_count');
                $song->increment($type === 'like' ? 'likes_count' : 'dislikes_count');
                $action = 'switched';
            } else {
                SongReaction::create([
                    'song_id'       => $song->id,
                    'identity_type' => $identityType,
                    'identity_id'   => $identityId,
                    'type'          => $type,
                ]);
                $song->increment($type === 'like' ? 'likes_count' : 'dislikes_count');
            }

            // increment()/decrement() خود مقدار in-memory مدل رو هم آپدیت می‌کنن؛ نیازی به refresh() نیست
            return [
                'likes_count'    => $song->likes_count,
                'dislikes_count' => $song->dislikes_count,
                'user_reaction'  => $userReaction,
                'action'         => $action,
            ];
        });
    }

    public function getUserReaction(Song $song, string $identityType, string $identityId): ?string
    {
        return SongReaction::where('song_id', $song->id)
            ->where('identity_type', $identityType)
            ->where('identity_id', $identityId)
            ->value('type');
    }
}
