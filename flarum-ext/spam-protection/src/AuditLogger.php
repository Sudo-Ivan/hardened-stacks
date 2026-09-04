<?php

namespace HardenedStacks\SpamProtection;

use Carbon\Carbon;
use Illuminate\Database\ConnectionInterface;
use Throwable;

class AuditLogger
{
    public function __construct(
        private ConnectionInterface $db
    ) {
    }

    /**
     * @param list<string> $actions
     */
    public function record(
        string $kind,
        ?int $postId,
        ?int $discussionId,
        ?int $userId,
        SpamVerdict $verdict,
        string $status = 'ok',
        ?string $error = null
    ): void {
        try {
            if (! $this->db->getSchemaBuilder()->hasTable('pmg_spam_audits')) {
                return;
            }

            $this->db->table('pmg_spam_audits')->insert([
                'kind' => mb_substr($kind, 0, 32),
                'post_id' => $postId,
                'discussion_id' => $discussionId,
                'user_id' => $userId,
                'is_spam' => $verdict->isSpam ? 1 : 0,
                'confidence' => max(0, min(100, $verdict->confidence)),
                'actions' => mb_substr(implode(',', $verdict->actions), 0, 255),
                'reason' => mb_substr($verdict->reason, 0, 512),
                'status' => mb_substr($status, 0, 32),
                'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
                'created_at' => Carbon::now(),
            ]);
        } catch (Throwable) {
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        if (! $this->db->getSchemaBuilder()->hasTable('pmg_spam_audits')) {
            return [];
        }

        $limit = max(1, min(200, $limit));

        return $this->db->table('pmg_spam_audits')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
